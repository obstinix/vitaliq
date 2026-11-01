<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\HealthIdService;
use Vitaliq\Services\LabAnalyticsService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\LabResourcesService;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/** Laboratory workspace (features 11-20). Routes require role lab or admin plus a lab.* permission. */
final class LabController extends Controller
{
    private function lab(): LaboratoryService
    {
        return $this->app->service(LaboratoryService::class);
    }

    private function res(): LabResourcesService
    {
        return $this->app->service(LabResourcesService::class);
    }

    /** @param array<string,mixed> $data */
    private function screen(string $template, string $title, array $data = [], int $status = 200): Response
    {
        return $this->view('lab/' . $template, $data + ['title' => $title], $status, 'app');
    }

    /** Run a workflow action and come back with a flash message. */
    private function act(callable $fn, string $ok, string $back): Response
    {
        try {
            $fn();
            $this->flash('success', $ok);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        } catch (ValidationFailed $e) {
            $this->flash('error', implode(' ', $e->errors));
        }
        return $this->redirect($back);
    }

    public function overview(Request $request): Response
    {
        $verify = $this->lab()->verificationQueue($this->userId());
        return $this->screen('overview', 'Laboratory', [
            'workload' => $this->app->service(LabAnalyticsService::class)->workload(),
            'samples' => $this->lab()->samples(),
            'verify' => array_slice($verify, 0, 6),
            'verifyTotal' => count($verify),
            'stock' => array_values(array_filter($this->res()->inventory(), static fn ($i) => $i['state'] !== 'ok')),
            'equipment' => array_values(array_filter($this->res()->equipment(), static fn ($e) => $e['status'] !== 'operational' || in_array($e['calibration_state'], ['overdue', 'due'], true))),
        ]);
    }

    /* ------------------------------------------------------------- samples */

    public function samples(Request $request): Response
    {
        $status = $request->query('status');
        return $this->screen('samples', 'Samples', ['data' => $this->lab()->samples($status), 'status' => $status]);
    }

    public function scanForm(Request $request): Response
    {
        // The camera is allowed on this page only, for browser barcode scanning.
        return $this->screen('scan', 'Scan a barcode', ['scripts' => ['lab-scan']])
            ->withHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
    }

    public function scan(Request $request): Response
    {
        $code = preg_replace('/\D/', '', $request->input('barcode')) ?? '';
        if (!LaboratoryService::barcodeValid($code)) {
            return $this->screen('scan', 'Scan a barcode', ['scripts' => ['lab-scan'], 'errors' => ['barcode' => 'That is not a valid 12-digit sample barcode. Check the digits and scan again.'], 'old' => ['barcode' => $request->input('barcode')]], 422)
                ->withHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        }
        $sample = $this->lab()->sampleByBarcode($code);
        if ($sample === null) {
            return $this->screen('scan', 'Scan a barcode', ['scripts' => ['lab-scan'], 'errors' => ['barcode' => "No sample has barcode {$code}."], 'old' => ['barcode' => $code]], 404)
                ->withHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        }
        return $this->redirect('/lab/samples/' . (int) $sample['id']);
    }

    public function sample(Request $request, string $id): Response
    {
        $s = $this->app->db->one('SELECT barcode FROM lab_samples WHERE id = :id', ['id' => (int) $id]);
        $sample = $s !== null ? $this->lab()->sampleByBarcode((string) $s['barcode']) : null;
        if ($sample === null) {
            $this->notFound();
        }
        return $this->screen('sample', 'Sample ' . $sample['barcode'], [
            'sample' => $sample,
            'events' => $this->lab()->sampleEvents((int) $id),
            'next' => LaboratoryService::SAMPLE_FLOW[$sample['status']] ?? [],
            'replacement' => $this->app->db->value('SELECT id FROM lab_samples WHERE replaces_sample_id = :id', ['id' => (int) $id]),
        ]);
    }

    public function moveSample(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->lab()->moveSample((int) $id, $request->input('to'), $this->actor(), $request->input('note'), $request->body),
            'Sample updated.', '/lab/samples/' . (int) $id);
    }

    public function recollect(Request $request, string $id): Response
    {
        try {
            $new = $this->lab()->recollect((int) $id, $this->actor());
            $this->flash('success', 'Replacement sample created. Print its label.');
            return $this->redirect('/lab/samples/' . $new);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/lab/samples/' . (int) $id);
        }
    }

    public function label(Request $request, string $id): Response
    {
        $s = $this->app->db->one(
            'SELECT s.*, o.order_no, o.priority, p.full_name, p.date_of_birth FROM lab_samples s JOIN lab_orders o ON o.id = s.order_id
               JOIN patients p ON p.id = o.patient_id WHERE s.id = :id', ['id' => (int) $id]);
        if ($s === null) {
            $this->notFound();
        }
        return $this->view('lab/label', ['title' => 'Label ' . $s['barcode'], 'sample' => $s, 'qr' => HealthIdService::qrSvg((string) $s['barcode'])], 200, 'print');
    }

    /* -------------------------------------------------------------- orders */

    public function orders(Request $request): Response
    {
        $status = $request->query('status');
        return $this->screen('orders', 'Test orders', [
            'data' => $this->lab()->orders($status, $request->query('q'), $this->page($request)),
            'status' => $status, 'q' => $request->query('q'), 'page' => $this->page($request),
        ]);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function newOrder(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $q = trim($request->query('q', (string) ($old['q'] ?? '')));
        $matches = [];
        if (mb_strlen($q) >= 3) {
            $like = '%' . \Vitaliq\Core\Database::likeEscape($q) . '%';
            $matches = $this->app->db->all(
                'SELECT p.id, p.full_name, p.date_of_birth, p.sex, p.mobile FROM patients p
                  WHERE p.full_name LIKE :a OR p.mobile LIKE :b OR EXISTS (SELECT 1 FROM test_requests r WHERE r.patient_id = p.id AND r.reference = :c)
                  ORDER BY p.full_name LIMIT 15',
                ['a' => $like, 'b' => $like, 'c' => strtoupper($q)]
            );
        }
        $tests = [];
        foreach ($this->lab()->tests() as $t) {
            $tests[$t['section']][] = $t;
        }
        return $this->screen('order-new', 'New test order', [
            'q' => $q, 'matches' => $matches, 'tests' => $tests, 'errors' => $errors, 'old' => $old,
            'now' => $this->app->clock->now(),
        ], $status);
    }

    public function createOrder(Request $request): Response
    {
        try {
            $id = $this->lab()->createOrder($request->body, $this->actor());
        } catch (ValidationFailed $e) {
            return $this->newOrder($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Order created. Print the sample labels.');
        return $this->redirect('/lab/orders/' . $id);
    }

    public function order(Request $request, string $id): Response
    {
        $o = $this->lab()->order((int) $id);
        if ($o === null) {
            $this->notFound();
        }
        return $this->screen('order', 'Order ' . $o['order_no'], [
            'order' => $o,
            'age' => PatientService::age((string) $o['date_of_birth'], $this->app->clock->now()),
            'canVerify' => $this->app->can('lab.verify'),
        ]);
    }

    public function enterResult(Request $request, string $id): Response
    {
        $orderId = (int) $this->app->db->value('SELECT order_id FROM lab_order_items WHERE id = :id', ['id' => (int) $id]);
        if ($orderId === 0) {
            $this->notFound();
        }
        try {
            $flag = $this->lab()->enterResult((int) $id, $request->input('value'), $request->input('comment'), $this->actor());
            $this->flash($flag === 'normal' ? 'success' : 'notice', 'Result saved' . ($flag === 'normal' ? '.' : ' and flagged ' . strtolower(LaboratoryService::FLAGS[$flag]) . '. It needs a second person to verify it.'));
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        } catch (ValidationFailed $e) {
            $this->flash('error', implode(' ', $e->errors));
        }
        return $this->redirect('/lab/orders/' . $orderId . '#item-' . (int) $id);
    }

    private function itemOrder(string $id): int
    {
        $orderId = (int) $this->app->db->value('SELECT order_id FROM lab_order_items WHERE id = :id', ['id' => (int) $id]);
        if ($orderId === 0) {
            $this->notFound();
        }
        return $orderId;
    }

    public function verifyItem(Request $request, string $id): Response
    {
        $back = $request->input('back') === 'queue' ? '/lab/verify' : '/lab/orders/' . $this->itemOrder($id);
        return $this->act(fn () => $this->lab()->verifyItem((int) $id, $this->actor()), 'Result verified.', $back);
    }

    public function returnItem(Request $request, string $id): Response
    {
        $back = $request->input('back') === 'queue' ? '/lab/verify' : '/lab/orders/' . $this->itemOrder($id);
        return $this->act(fn () => $this->lab()->returnItem((int) $id, $this->actor(), $request->input('reason')), 'Result sent back for re-entry.', $back);
    }

    public function rejectItem(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->lab()->rejectItem((int) $id, $this->actor(), $request->input('reason')), 'Test marked not reportable.', '/lab/orders/' . $this->itemOrder($id));
    }

    public function release(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->lab()->releaseOrder((int) $id, $this->actor()), 'Results released to the patient.', '/lab/orders/' . (int) $id);
    }

    public function cancel(Request $request, string $id): Response
    {
        return $this->act(fn () => $this->lab()->cancelOrder((int) $id, $this->actor(), $request->input('reason')), 'Order cancelled.', '/lab/orders/' . (int) $id);
    }

    public function verifyQueue(Request $request): Response
    {
        return $this->screen('verify', 'Awaiting verification', ['rows' => $this->lab()->verificationQueue($this->userId())]);
    }

    /* ----------------------------------------------------------- catalogue */

    public function catalogue(Request $request): Response
    {
        $tests = $this->lab()->tests(false);
        $ranges = [];
        foreach ($this->app->db->all('SELECT * FROM lab_reference_ranges ORDER BY test_id, sex') as $r) {
            $ranges[(int) $r['test_id']][] = $r;
        }
        return $this->screen('catalogue', 'Test catalogue', ['tests' => $tests, 'ranges' => $ranges]);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function catalogueTest(Request $request, string $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $test = $this->app->db->one('SELECT * FROM lab_tests WHERE id = :id', ['id' => (int) $id]);
        if ($test === null) {
            $this->notFound();
        }
        return $this->screen('catalogue-test', $test['name'], ['test' => $test, 'ranges' => $this->lab()->ranges((int) $id), 'errors' => $errors, 'old' => $old], $status);
    }

    public function saveRange(Request $request, string $id): Response
    {
        try {
            $this->lab()->saveRange((int) $id, (int) $request->input('range_id', '0'), $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->catalogueTest($request, $id, $e->errors, $request->body, 422);
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        $this->flash('success', 'Reference range saved. It applies to results entered from now on.');
        return $this->redirect('/lab/catalogue/' . (int) $id);
    }

    /* ----------------------------------------------------------- inventory */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function inventory(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->screen('inventory', 'Inventory', ['items' => $this->res()->inventory(), 'errors' => $errors, 'old' => $old], $status);
    }

    public function createItem(Request $request): Response
    {
        try {
            $id = $this->res()->createItem($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->inventory($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Item added. Record its first delivery.');
        return $this->redirect('/lab/inventory/' . $id);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function item(Request $request, string $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $item = $this->res()->item((int) $id);
        if ($item === null) {
            $this->notFound();
        }
        return $this->screen('inventory-item', $item['name'], ['item' => $item, 'errors' => $errors, 'old' => $old, 'today' => $this->app->clock->now()->format('Y-m-d')], $status);
    }

    public function receive(Request $request, string $id): Response
    {
        try {
            $this->res()->receive((int) $id, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->item($request, $id, $e->errors, $request->body, 422);
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        $this->flash('success', 'Delivery recorded.');
        return $this->redirect('/lab/inventory/' . (int) $id);
    }

    public function consume(Request $request, string $id): Response
    {
        try {
            $this->res()->consume((int) $id, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->item($request, $id, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Stock updated.');
        return $this->redirect('/lab/inventory/' . (int) $id);
    }

    public function writeOff(Request $request): Response
    {
        $n = $this->res()->writeOffExpired($this->userId());
        $this->flash('success', $n === 0 ? 'No expired stock to write off.' : "{$n} expired units written off.");
        return $this->redirect('/lab/inventory');
    }

    /* ----------------------------------------------------------- equipment */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function equipment(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->screen('equipment', 'Equipment', ['rows' => $this->res()->equipment(), 'errors' => $errors, 'old' => $old, 'today' => $this->app->clock->now()->format('Y-m-d')], $status);
    }

    public function createEquipment(Request $request): Response
    {
        try {
            $id = $this->res()->createEquipment($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->equipment($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Equipment added.');
        return $this->redirect('/lab/equipment/' . $id);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function equipmentItem(Request $request, string $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $e = $this->res()->equipmentItem((int) $id);
        if ($e === null) {
            $this->notFound();
        }
        return $this->screen('equipment-item', $e['name'], ['eq' => $e, 'errors' => $errors, 'old' => $old, 'today' => $this->app->clock->now()->format('Y-m-d')], $status);
    }

    public function logEquipment(Request $request, string $id): Response
    {
        try {
            $this->res()->log((int) $id, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->equipmentItem($request, $id, $e->errors, $request->body, 422);
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        $this->flash('success', 'Log entry saved.');
        return $this->redirect('/lab/equipment/' . (int) $id);
    }

    /* --------------------------------------------------------- performance */

    public function performance(Request $request): Response
    {
        $days = in_array((int) $request->query('days', '30'), [7, 30, 90], true) ? (int) $request->query('days', '30') : 30;
        $svc = $this->app->service(LabAnalyticsService::class);
        return $this->screen('performance', 'Workload and turnaround', [
            'perf' => $svc->performance($days),
            'workload' => $svc->workload(),
            
        ]);
    }
}
