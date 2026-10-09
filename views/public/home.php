<?php
use Vitaliq\Support\Labels;

$counts = $summary['counts'];
$stages = ['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed', 'result_available'];
?>
<section class="hero">
  <div class="wrap hero__grid">
    <div>
      <p class="eyebrow eyebrow--plain" data-reveal><span class="num">Vitaliq</span> COVID-19 information &amp; testing</p>
      <h1 data-hero-title>Health data, made <em>meaningful.</em></h1>
      <p class="lede" data-reveal>Clear guidance on COVID-19, drawn from WHO and CDC sources, and a testing service where every request has a reference, a named person responsible and a status you can check at any time.</p>
      <div class="cluster" data-reveal>
        <a class="btn btn--primary" href="<?= e(url('/test/new')) ?>">Request a test <span class="arrow" aria-hidden="true">→</span></a>
        <a class="btn btn--secondary" href="<?= e(url('/track')) ?>">Track a test</a>
        <a class="btn btn--ghost" href="<?= e(url('/symptoms')) ?>">Check symptoms</a>
      </div>
      <p class="hero__note" data-reveal>Already registered? <a href="<?= e(url('/login')) ?>">Sign in</a> to request again without re-entering your details.</p>
    </div>

    <figure class="rail-figure" data-rail aria-labelledby="rail-caption">
      <figcaption id="rail-caption"><span>Every request, six visible steps</span><span>Live counts</span></figcaption>
      <div class="rail-wrap">
      <span class="rail__fill" aria-hidden="true" data-rail-fill></span>
      <ol class="rail">
        <?php foreach ($stages as $stage): ?>
          <li class="is-done" data-rail-step>
            <span class="rail__node" aria-hidden="true"></span>
            <span class="rail__label"><?= e(Labels::STATUSES[$stage]) ?></span>
            <span class="rail__meta"><?= fmt_number($counts[$stage]) ?> now</span>
          </li>
        <?php endforeach; ?>
      </ol>
      </div>
      <p class="hint mt-4 mb-0">Counts are the requests currently at each step in this Vitaliq database.</p>
    </figure>
  </div>
</section>

<section class="wrap" aria-labelledby="figures-title">
  <h2 class="visually-hidden" id="figures-title">Platform figures</h2>
  <div class="figures">
    <div class="figure" data-reveal>
      <span class="figure__value" data-count="<?= (int) $summary['total'] ?>"><?= fmt_number($summary['total']) ?></span>
      <span class="figure__label">testing requests recorded</span>
    </div>
    <div class="figure" data-reveal>
      <span class="figure__value" data-count="<?= (int) $summary['in_pipeline'] ?>"><?= fmt_number($summary['in_pipeline']) ?></span>
      <span class="figure__label">requests in progress right now</span>
    </div>
    <div class="figure" data-reveal>
      <span class="figure__value" data-count="<?= (int) $summary['released'] ?>"><?= fmt_number($summary['released']) ?></span>
      <span class="figure__label">results released to patients</span>
    </div>
  </div>
  <p class="figures-note">
    Vitaliq platform records<?= $summary['demo_seeded_at'] ? ', including synthetic demonstration data seeded on ' . e(fmt_date($summary['demo_seeded_at'])) : '' ?>. These are not national COVID-19 statistics. <a href="<?= e(url('/statistics')) ?>">See the statistics page</a> for historical WHO data.
  </p>
</section>

<section class="section" aria-labelledby="signs-title">
  <div class="wrap split">
    <div>
      <p class="eyebrow"><span class="num">01</span> Know the signs</p>
      <h2 id="signs-title">Most cases are mild. Some signs are not.</h2>
      <p class="muted">WHO lists fever, chills and sore throat as the most common symptoms. They usually begin 3–6 days after exposure, and people can pass on the virus before they feel unwell.</p>
      <a class="btn btn--secondary mt-4" href="<?= e(url('/symptoms')) ?>">All symptoms and warning signs</a>
    </div>
    <div class="stack">
      <ul class="symptom-columns" aria-label="Commonly reported symptoms">
        <li>Fever or chills</li>
        <li>Sore throat</li>
        <li>Cough</li>
        <li>Fatigue</li>
        <li>Muscle or body aches</li>
        <li>Headache</li>
        <li>Congestion or runny nose</li>
        <li>New loss of taste or smell</li>
      </ul>
      <div class="callout callout--urgent" data-reveal>
        <h3>Get emergency care for</h3>
        <p>Severe difficulty breathing, persistent chest pain or pressure, new confusion, difficulty staying awake, or pale, grey or bluish lips, skin or nail beds. In India, call <strong>112</strong>.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section--tint" aria-labelledby="how-title">
  <div class="wrap">
    <p class="eyebrow"><span class="num">02</span> How testing works</p>
    <h2 id="how-title">From request to authorised result.</h2>
    <ol class="ledger ledger--steps mt-6">
      <li data-reveal>
        <span class="ledger__num">01</span>
        <div><h3>Request</h3><p>Tell us who needs the test, why, and where to collect the sample. You receive a reference such as <span class="ref">VQ-261010-7K3MP</span>.</p></div>
      </li>
      <li data-reveal>
        <span class="ledger__num">02</span>
        <div><h3>Collection</h3><p>An administrator assigns a phlebotomist, who records the specimen and tube barcode when the sample is taken.</p></div>
      </li>
      <li data-reveal>
        <span class="ledger__num">03</span>
        <div><h3>Laboratory</h3><p>The sample is processed and the result recorded. Nothing is shown to the patient until it is authorised.</p></div>
      </li>
      <li data-reveal>
        <span class="ledger__num">04</span>
        <div><h3>Result</h3><p>Sign in, or use your reference with your mobile number and date of birth, to see the result and a printable report.</p></div>
      </li>
    </ol>
  </div>
</section>

<section class="section" aria-labelledby="news-title">
  <div class="wrap split split--wide-left">
    <div>
      <p class="eyebrow"><span class="num">03</span> Announcements</p>
      <h2 id="news-title">Updates, with their sources.</h2>
      <?php if ($announcements === []): ?>
        <div class="empty mt-5"><h3>No announcements yet</h3><p>Published updates will appear here with their source and date.</p></div>
      <?php else: ?>
        <ul class="notice-list mt-5">
          <?php foreach ($announcements as $a): ?>
            <li data-reveal>
              <time class="notice-date" datetime="<?= e($a['published_on']) ?>"><?= e(fmt_date($a['published_on'])) ?></time>
              <div>
                <h3><?= e($a['title']) ?></h3>
                <p><?= e($a['summary']) ?></p>
                <?php if ($a['source_url']): ?><a href="<?= e($a['source_url']) ?>" rel="noopener" target="_blank">Source: <?= e($a['source_name']) ?><span class="visually-hidden"> (opens in a new tab)</span></a><?php endif; ?>
                <?php if ((int) $a['is_demo'] === 1): ?> <span class="tag tag--demo">Demo notice</span><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-5"><a href="<?= e(url('/announcements')) ?>">All announcements</a></p>
      <?php endif; ?>
    </div>
    <aside aria-labelledby="sources-title">
      <p class="eyebrow"><span class="num">04</span> Sources</p>
      <h3 id="sources-title">Where our guidance comes from</h3>
      <ul class="source-list">
        <li><a href="https://www.who.int/news-room/fact-sheets/detail/coronavirus-disease-(covid-19)" rel="noopener" target="_blank">WHO fact sheet: COVID-19</a><span class="source-meta">World Health Organization · updated 27 Nov 2025</span></li>
        <li><a href="https://www.cdc.gov/covid/signs-symptoms/index.html" rel="noopener" target="_blank">CDC: Symptoms of COVID-19</a><span class="source-meta">US Centers for Disease Control and Prevention · updated 10 Mar 2025</span></li>
        <li><a href="https://www.cdc.gov/covid/testing/index.html" rel="noopener" target="_blank">CDC: Testing for COVID-19</a><span class="source-meta">US Centers for Disease Control and Prevention · updated 10 Mar 2025</span></li>
        <li><a href="https://www.mohfw.gov.in/" rel="noopener" target="_blank">Ministry of Health and Family Welfare</a><span class="source-meta">Government of India · national guidance</span></li>
      </ul>
    </aside>
  </div>
</section>
