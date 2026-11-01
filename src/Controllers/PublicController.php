<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AnnouncementService;
use Vitaliq\Services\CommentService;
use Vitaliq\Services\StatsService;
use Vitaliq\Services\ValidationFailed;

final class PublicController extends Controller
{
    /** Public aggregates below this are shown as "<5" to avoid small-cell disclosure. */
    public const SMALL_CELL = 5;

    public function home(Request $request): Response
    {
        return $this->view('public/home', [
            'title' => 'Health data, made meaningful',
            'summary' => $this->app->service(StatsService::class)->publicSummary(),
            'announcements' => $this->app->service(AnnouncementService::class)->published(3),
            'pageClass' => 'page-home',
        ]);
    }

    public function overview(Request $request): Response
    {
        return $this->view('public/overview', ['title' => 'COVID-19 overview']);
    }

    public function symptoms(Request $request): Response
    {
        return $this->view('public/symptoms', ['title' => 'Symptoms and warning signs']);
    }

    public function prevention(Request $request): Response
    {
        return $this->view('public/prevention', ['title' => 'Prevention']);
    }

    public function testing(Request $request): Response
    {
        return $this->view('public/testing', ['title' => 'Testing information']);
    }

    public function faq(Request $request): Response
    {
        return $this->view('public/faq', ['title' => 'Frequently asked questions']);
    }

    public function contact(Request $request): Response
    {
        return $this->view('public/contact', ['title' => 'Contact and help']);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('public/privacy', ['title' => 'How Vitaliq uses your information']);
    }

    public function announcements(Request $request): Response
    {
        return $this->view('public/announcements', [
            'title' => 'Announcements',
            'announcements' => $this->app->service(AnnouncementService::class)->published(50),
        ]);
    }

    public function statistics(Request $request): Response
    {
        $stats = $this->app->service(StatsService::class);
        $who = json_decode((string) file_get_contents(VQ_ROOT . '/public/assets/data/who-india-monthly.json'), true);
        return $this->view('public/statistics', [
            'title' => 'Testing statistics',
            'summary' => $stats->publicSummary(),
            'states' => $stats->stateSummary(),
            'series' => $stats->dailySeries(30),
            'who' => $who,
            'smallCell' => self::SMALL_CELL,
            'scripts' => ['charts'],
        ]);
    }

    public function statisticsJson(Request $request): Response
    {
        $stats = $this->app->service(StatsService::class);
        return Response::json([
            'note' => 'Aggregated Vitaliq platform records. Not official public-health statistics.',
            'daily' => $stats->dailySeries(30),
            'status_counts' => $stats->statusCounts(),
        ]);
    }

    public function community(Request $request): Response
    {
        return $this->renderCommunity($request);
    }

    public function postComment(Request $request): Response
    {
        try {
            $this->app->service(CommentService::class)->submit($request->body, $this->app->auth()->user());
        } catch (ValidationFailed $e) {
            return $this->renderCommunity($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Thank you. Your comment has been received and will appear once a moderator approves it.');
        return $this->redirect('/community#comment-form');
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderCommunity(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $comments = $this->app->service(CommentService::class);
        $topic = $request->query('topic');
        $page = $this->page($request);
        $user = $this->app->auth()->user();
        if ($old === [] && $user !== null) {
            $old = ['author_name' => $user['full_name']];
        }
        return $this->view('public/community', [
            'title' => 'Community',
            'list' => $comments->approved($page, $topic),
            'topic' => $topic,
            'page' => $page,
            'stamp' => $comments->formStamp(),
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    /** Choose the interface language (feature 38); remembered on the account when signed in. */
    public function setLocale(Request $request): Response
    {
        $locale = $request->input('locale');
        if (!isset(\Vitaliq\Support\Lang::LOCALES[$locale])) {
            $locale = 'en';
        }
        $this->app->session()->set('locale', $locale);
        $id = $this->app->auth()->id();
        if ($id !== null) {
            $this->app->db->execute('UPDATE users SET locale = :l WHERE id = :id', ['l' => $locale, 'id' => $id]);
        }
        $back = $request->input('back');
        return $this->redirect(preg_match('#^/[A-Za-z0-9/_-]*$#', $back) ? $back : '/');
    }
}
