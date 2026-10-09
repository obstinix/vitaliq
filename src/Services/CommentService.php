<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Validator;

/**
 * Public comments with pre-moderation: nothing is shown publicly until an
 * administrator approves it. Spam controls: a hidden honeypot field, a
 * signed form timestamp (too-fast or stale submissions are refused), a
 * per-IP rate limit and a cap on links.
 */
final class CommentService
{
    public const MIN_SECONDS = 3;
    public const MAX_AGE_SECONDS = 7200;
    public const MAX_PER_WINDOW = 3;
    public const WINDOW_SECONDS = 600;

    public function __construct(private readonly App $app)
    {
    }

    public function formStamp(): string
    {
        $ts = (string) $this->app->clock->now()->getTimestamp();
        return $ts . '.' . hash_hmac('sha256', 'comment|' . $ts, (string) $this->app->config['key']);
    }

    private function stampAge(string $stamp): ?int
    {
        if (!preg_match('/^([0-9]{9,11})\.([a-f0-9]{64})$/', $stamp, $m)) {
            return null;
        }
        $expected = hash_hmac('sha256', 'comment|' . $m[1], (string) $this->app->config['key']);
        if (!hash_equals($expected, $m[2])) {
            return null;
        }
        return $this->app->clock->now()->getTimestamp() - (int) $m[1];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return 'pending'|'discarded'
     * @throws ValidationFailed
     */
    public function submit(array $input, ?array $user): string
    {
        // Honeypot: real visitors never see or fill this field. Pretend success.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return 'discarded';
        }
        $age = $this->stampAge((string) ($input['stamp'] ?? ''));
        if ($age === null || $age > self::MAX_AGE_SECONDS) {
            throw new ValidationFailed(['body' => 'This form has expired. Please reload the page and try again.']);
        }
        if ($age < self::MIN_SECONDS) {
            throw new ValidationFailed(['body' => 'That was quick. Please take a moment to review your comment and submit again.']);
        }

        $v = new Validator($input);
        $name = $v->rule('author_name', 'full_name', true, 'Your name');
        $email = $v->rule('email', 'email', false);
        $topic = $v->choice('topic', Labels::COMMENT_TOPICS, 'Topic');
        $body = $v->text('body', 'Comment', 1000, true, 10);
        if ($body !== null && preg_match_all('~https?://|www\.~i', $body) > 2) {
            $v->addError('body', 'Please include no more than two links.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }

        if (!$this->app->rateLimiter()->hit('comment:' . $this->app->ipHash(), self::MAX_PER_WINDOW, self::WINDOW_SECONDS)) {
            throw new ValidationFailed(['body' => 'You have posted several comments recently. Please try again in a few minutes.']);
        }

        $id = $this->app->db->insert(
            'INSERT INTO comments (user_id, author_name, email, topic, body, status, ip_hash, created_at)
             VALUES (:u, :n, :e, :t, :b, \'pending\', :ip, :c)',
            [
                'u' => $user['id'] ?? null, 'n' => $name, 'e' => $email, 't' => $topic, 'b' => $body,
                'ip' => $this->app->ipHash(), 'c' => $this->app->clock->nowSql(),
            ]
        );
        $this->app->service(NotificationService::class)->notifyRole('admin', 'comment.pending',
            'Comment awaiting moderation', mb_substr((string) $body, 0, 120), '/admin/comments');
        $this->app->service(AuditLog::class)->record('comment.submitted', 'comment', $id, [], $user['id'] ?? null);
        return 'pending';
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function approved(int $page, string $topic = '', int $perPage = 10): array
    {
        $where = "status = 'approved'";
        $params = [];
        if ($topic !== '' && isset(Labels::COMMENT_TOPICS[$topic])) {
            $where .= ' AND topic = :t';
            $params['t'] = $topic;
        }
        $total = (int) $this->app->db->value("SELECT COUNT(*) FROM comments WHERE {$where}", $params);
        $rows = $this->app->db->all(
            "SELECT id, author_name, topic, body, created_at, is_demo FROM comments WHERE {$where}
              ORDER BY created_at DESC, id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function forModeration(string $status, int $page, int $perPage = 20): array
    {
        $status = isset(Labels::COMMENT_STATUSES[$status]) ? $status : 'pending';
        $total = (int) $this->app->db->value('SELECT COUNT(*) FROM comments WHERE status = :s', ['s' => $status]);
        $rows = $this->app->db->all(
            'SELECT c.*, u.full_name AS moderator_name FROM comments c
               LEFT JOIN users u ON u.id = c.moderated_by_user_id
              WHERE c.status = :s ORDER BY c.created_at DESC, c.id DESC LIMIT :lim OFFSET :off',
            ['s' => $status, 'lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,int> */
    public function statusCounts(): array
    {
        $out = array_fill_keys(array_keys(Labels::COMMENT_STATUSES), 0);
        foreach ($this->app->db->all('SELECT status, COUNT(*) AS n FROM comments GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public function moderate(int $id, string $decision, ?string $note): void
    {
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'spam' => 'spam'];
        if (!isset($map[$decision])) {
            throw new WorkflowException('Unknown moderation action.');
        }
        $changed = $this->app->db->execute(
            'UPDATE comments SET status = :s, moderated_by_user_id = :u, moderated_at = :t, moderation_note = :n WHERE id = :id',
            [
                's' => $map[$decision], 'u' => $this->app->auth()->id(), 't' => $this->app->clock->nowSql(),
                'n' => $note === null ? null : mb_substr(trim($note), 0, 300), 'id' => $id,
            ]
        );
        if ($changed === 0 && $this->app->db->value('SELECT 1 FROM comments WHERE id = :id', ['id' => $id]) === null) {
            throw new WorkflowException('Comment not found.');
        }
        $this->app->service(AuditLog::class)->record('comment.' . $map[$decision], 'comment', $id);
    }
}
