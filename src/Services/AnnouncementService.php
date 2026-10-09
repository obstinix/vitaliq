<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Validation\Validator;

final class AnnouncementService
{
    public const CATEGORIES = [
        'public_health' => 'Public-health update',
        'service' => 'Vitaliq service notice',
    ];

    public function __construct(private readonly App $app)
    {
    }

    /** @return list<array<string,mixed>> */
    public function published(int $limit = 20): array
    {
        return $this->app->db->all(
            'SELECT * FROM announcements WHERE is_published = 1 ORDER BY published_on DESC, id DESC LIMIT :lim',
            ['lim' => $limit]
        );
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->app->db->all(
            'SELECT a.*, u.full_name AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by_user_id
              ORDER BY a.published_on DESC, a.id DESC LIMIT 200'
        );
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function create(array $input): int
    {
        $v = new Validator($input);
        $category = $v->choice('category', self::CATEGORIES, 'Category');
        $title = $v->text('title', 'Title', 160, true, 5);
        $summary = $v->text('summary', 'Summary', 400, true, 10);
        $sourceName = $v->text('source_name', 'Source name', 120, false);
        $sourceUrl = trim((string) ($input['source_url'] ?? ''));
        if ($sourceUrl !== '' && (!filter_var($sourceUrl, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $sourceUrl) || strlen($sourceUrl) > 300)) {
            $v->addError('source_url', 'Use a full https:// link of up to 300 characters.');
        }
        if ($category === 'public_health' && ($sourceUrl === '' || $sourceName === null)) {
            $v->addError('source_url', 'Public-health updates must cite a source name and link.');
        }
        $date = $v->date('published_on', 'Publication date', null, $this->app->clock->now());
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $id = $this->app->db->insert(
            'INSERT INTO announcements (category, title, summary, source_name, source_url, published_on, is_demo, is_published, created_by_user_id)
             VALUES (:c, :t, :s, :sn, :su, :d, 0, 1, :u)',
            ['c' => $category, 't' => $title, 's' => $summary, 'sn' => $sourceName, 'su' => $sourceUrl ?: null,
             'd' => $date, 'u' => $this->app->auth()->id()]
        );
        $this->app->service(AuditLog::class)->record('announcement.created', 'announcement', $id);
        return $id;
    }

    public function togglePublished(int $id): void
    {
        $changed = $this->app->db->execute('UPDATE announcements SET is_published = 1 - is_published WHERE id = :id', ['id' => $id]);
        if ($changed === 0) {
            throw new WorkflowException('Announcement not found.');
        }
        $this->app->service(AuditLog::class)->record('announcement.toggled', 'announcement', $id);
    }
}
