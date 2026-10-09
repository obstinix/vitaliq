<?php
$faqs = [
    ['Do I need an account to request a test?', 'No. Choose “Request a test” and fill in the form. You will get a reference number, and you can track the request with that reference, your mobile number and date of birth. With an account, your details are saved, you can see your full history, and you get notifications as your request moves forward.'],
    ['I requested a test without an account. Can I add it to my account later?', 'Yes. Register with the same mobile number, then use “Link a request” in your dashboard with the reference and the date of birth you gave.'],
    ['Which test should I choose?', 'RT-PCR is the most sensitive and is often required for travel or procedures. A rapid antigen test is faster but can miss infections, so a negative antigen result may need repeating. The testing page explains the difference.'],
    ['When will I see my result?', 'Only after the laboratory records it and an authorised staff member releases it. Your request shows each step as it happens, so you can see where it is.'],
    ['Can I cancel a request?', 'Yes, as long as the sample has not been collected. Open the request in your dashboard and choose “Cancel this request”. Without an account, contact the helpdesk with your reference.'],
    ['Who can see my information?', 'You, and the Vitaliq staff who need it to process your test. Results are never shown to anyone else, and staff actions on your request are recorded. The privacy page has the details.'],
    ['Are the statistics on this site official figures?', 'No. Platform statistics are counted from requests in this Vitaliq database, which may include synthetic demonstration records. The historical chart for India uses data that WHO publishes, with its source and download date shown beside it.'],
    ['Why is my comment not showing?', 'Every comment is reviewed by a moderator before it is published. This keeps out spam and misinformation.'],
];
?>
<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Help</span> Frequently asked questions</p>
    <h1>Answers before you ask.</h1>
  </div>
</header>
<div class="wrap wrap--narrow section">
  <div class="faq">
    <?php foreach ($faqs as $i => [$q, $a]): ?>
      <details<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= e($q) ?></summary>
        <div class="answer"><p><?= e($a) ?></p></div>
      </details>
    <?php endforeach; ?>
  </div>
  <p class="mt-6">Still unsure? <a href="<?= e(url('/contact')) ?>">Contact and help</a>.</p>
</div>
