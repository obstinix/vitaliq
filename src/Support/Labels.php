<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * Human-readable labels for database enum values. Keeping them here means
 * validation whitelists and UI copy cannot drift apart.
 */
final class Labels
{
    public const TEST_TYPES = [
        'rt_pcr' => 'RT-PCR (laboratory molecular test)',
        'rapid_antigen' => 'Rapid antigen test',
    ];

    public const TEST_TYPES_SHORT = [
        'rt_pcr' => 'RT-PCR',
        'rapid_antigen' => 'Rapid antigen',
    ];

    public const REASONS = [
        'symptoms' => 'I have symptoms',
        'exposure' => 'Close contact with a confirmed case',
        'travel' => 'Travel requirement',
        'pre_procedure' => 'Before a medical procedure',
        'screening' => 'Workplace or routine screening',
        'other' => 'Other',
    ];

    public const EXPOSURE = [
        'yes' => 'Yes',
        'no' => 'No',
        'unsure' => 'Not sure',
    ];

    public const SYMPTOMS = [
        'fever' => 'Fever',
        'chills' => 'Chills',
        'sore_throat' => 'Sore throat',
        'cough' => 'Cough',
        'breathlessness' => 'Shortness of breath',
        'fatigue' => 'Fatigue',
        'body_aches' => 'Muscle or body aches',
        'headache' => 'Headache',
        'congestion' => 'Congestion or runny nose',
        'loss_taste_smell' => 'New loss of taste or smell',
        'nausea' => 'Nausea or vomiting',
        'diarrhoea' => 'Diarrhoea',
    ];

    public const COLLECTION_MODES = [
        'home' => 'Home sample collection',
        'centre' => 'Visit a collection centre',
    ];

    public const SLOTS = [
        'morning' => 'Morning (8 am – 12 pm)',
        'afternoon' => 'Afternoon (12 – 4 pm)',
        'evening' => 'Evening (4 – 7 pm)',
    ];

    public const SEX = [
        'undisclosed' => 'Prefer not to say',
        'female' => 'Female',
        'male' => 'Male',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'assigned' => 'Phlebotomist assigned',
        'sample_collected' => 'Sample collected',
        'in_progress' => 'Testing in progress',
        'completed' => 'Result recorded',
        'result_available' => 'Result available',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ];

    /** One-line explanation shown to patients beside each status. */
    public const STATUS_HELP = [
        'submitted' => 'We have your request and will assign a phlebotomist.',
        'assigned' => 'A phlebotomist has been scheduled to collect your sample.',
        'sample_collected' => 'Your sample has been collected and is on its way to the lab.',
        'in_progress' => 'The laboratory is processing your sample.',
        'completed' => 'Your result has been recorded and is awaiting authorisation.',
        'result_available' => 'Your authorised result is ready to view.',
        'cancelled' => 'This request was cancelled.',
        'rejected' => 'This request could not be processed.',
    ];

    public const SPECIMENS = [
        'nasopharyngeal' => 'Nasopharyngeal swab',
        'oropharyngeal' => 'Oropharyngeal swab',
        'nasal' => 'Anterior nasal swab',
        'saliva' => 'Saliva',
    ];

    public const OUTCOMES = [
        'negative' => 'Not detected (negative)',
        'positive' => 'Detected (positive)',
        'inconclusive' => 'Inconclusive',
        'invalid' => 'Invalid sample',
    ];

    public const ROLES = [
        'patient' => 'Patient',
        'phlebotomist' => 'Phlebotomist',
        'admin' => 'Administrator',
        'qa' => 'QA analyst',
    ];

    public const COMMENT_TOPICS = [
        'general' => 'General',
        'testing' => 'Testing experience',
        'prevention' => 'Prevention',
        'website' => 'This website',
    ];

    public const COMMENT_STATUSES = [
        'pending' => 'Awaiting review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'spam' => 'Spam',
    ];

    public const SEVERITIES = [
        'critical' => 'Critical',
        'major' => 'Major',
        'minor' => 'Minor',
        'trivial' => 'Trivial',
    ];

    public const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    public const DEFECT_STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'wont_fix' => "Won't fix",
    ];

    public const DEFECT_ORIGINS = [
        'development_testing' => 'Found during development testing',
        'demo' => 'Demonstration record',
        'user_report' => 'Reported in the QA lab',
    ];

    public const CASE_TYPES = [
        'positive' => 'Positive',
        'negative' => 'Negative',
        'boundary' => 'Boundary',
        'malformed' => 'Malformed',
        'whitespace' => 'Whitespace',
        'unicode' => 'Unicode',
        'security' => 'Security',
        'functional' => 'Functional',
    ];

    /** @param array<string,string> $map */
    public static function get(array $map, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }
        return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
