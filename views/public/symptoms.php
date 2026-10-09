<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Guide</span> Symptoms and warning signs</p>
    <h1>Symptoms range from a sore throat to an emergency.</h1>
    <p class="lede">WHO says symptoms usually begin 3–6 days after exposure; CDC gives a wider range of 2–14 days. Symptoms alone cannot tell COVID-19 apart from flu or a cold. A test can.</p>
  </div>
</header>

<div class="wrap section">
  <div class="callout callout--urgent mb-5" role="note">
    <h2>Emergency warning signs: get medical care now</h2>
    <ul class="mb-0">
      <li>Trouble breathing, especially at rest</li>
      <li>Chest pain or pressure that does not go away</li>
      <li>New confusion, or being unable to wake or stay awake</li>
      <li>Pale, grey or bluish lips, nail beds or skin (how this looks depends on skin tone)</li>
      <li>Loss of speech or movement</li>
    </ul>
    <p class="mt-4">In India, call <strong>112</strong> for emergency services.</p>
  </div>

  <div class="split split--even mt-6">
    <section aria-labelledby="common">
      <p class="eyebrow"><span class="num">A</span> Most common</p>
      <h2 id="common">Fever, chills and sore throat</h2>
      <p class="muted">WHO lists these three as the most common symptoms.</p>
    </section>
    <section aria-labelledby="others">
      <p class="eyebrow"><span class="num">B</span> Also reported</p>
      <h2 id="others">Other symptoms</h2>
      <ul class="symptom-columns">
        <li>Cough</li>
        <li>Shortness of breath</li>
        <li>Fatigue</li>
        <li>Muscle or body aches</li>
        <li>Headache</li>
        <li>Congestion, runny nose or sneezing</li>
        <li>New loss of taste or smell</li>
        <li>Sore or red eyes</li>
        <li>Dizziness</li>
        <li>Chest tightness</li>
        <li>Hoarse voice</li>
        <li>Numbness or tingling</li>
        <li>Nausea, vomiting or diarrhoea</li>
        <li>Difficulty sleeping</li>
      </ul>
      <p class="hint mt-4">This list is not complete, and the pattern of symptoms can change with new variants.</p>
    </section>
  </div>

  <section class="mt-6" aria-labelledby="what-now">
    <p class="eyebrow"><span class="num">C</span> If you have symptoms</p>
    <h2 id="what-now">What to do next</h2>
    <ol class="ledger">
      <li><span class="ledger__num">01</span><div><h3>Stay away from others</h3><p>Stay home if you can, especially away from people at higher risk of severe illness.</p></div></li>
      <li><span class="ledger__num">02</span><div><h3>Get tested</h3><p>Testing tells you whether it is COVID-19 and helps you protect the people around you. <a href="<?= e(url('/testing')) ?>">Which test?</a></p></div></li>
      <li><span class="ledger__num">03</span><div><h3>Wear a well-fitted mask around others</h3><p>Cover coughs and sneezes, and wash your hands often.</p></div></li>
      <li><span class="ledger__num">04</span><div><h3>Watch for warning signs</h3><p>If any emergency warning sign appears, get medical care immediately.</p></div></li>
    </ol>
    <div class="cluster mt-5">
      <a class="btn btn--primary" href="<?= e(url('/test/new')) ?>">Request a test</a>
      <a class="btn btn--secondary" href="<?= e(url('/prevention')) ?>">Prevention advice</a>
    </div>
  </section>

  <?= $view->partial('sources', ['sources' => [
      ['CDC: Symptoms of COVID-19', 'https://www.cdc.gov/covid/signs-symptoms/index.html', 'US Centers for Disease Control and Prevention', '10 Mar 2025'],
      ['WHO fact sheet: Coronavirus disease (COVID-19)', 'https://www.who.int/news-room/fact-sheets/detail/coronavirus-disease-(covid-19)', 'World Health Organization', '27 Nov 2025'],
  ]]) ?>
</div>
