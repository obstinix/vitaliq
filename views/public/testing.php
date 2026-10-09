<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Guide</span> Testing information</p>
    <h1>Choosing a test, and reading the result.</h1>
    <p class="lede">Vitaliq offers the two kinds of test used to find a current infection: a laboratory molecular test (RT-PCR) and a rapid antigen test.</p>
  </div>
</header>

<div class="wrap section">
  <div class="table-wrap">
    <table class="table table--stack">
      <caption>The two test types compared</caption>
      <thead><tr><th scope="col">&nbsp;</th><th scope="col">RT-PCR (molecular, NAAT)</th><th scope="col">Rapid antigen</th></tr></thead>
      <tbody>
        <tr><th scope="row">What it detects</th><td data-label="RT-PCR">The virus’s genetic material</td><td data-label="Antigen">Proteins from the virus</td></tr>
        <tr><th scope="row">Sensitivity</th><td data-label="RT-PCR">Highest; CDC calls it the “gold standard”</td><td data-label="Antigen">Lower, especially without symptoms</td></tr>
        <tr><th scope="row">Time to result</th><td data-label="RT-PCR">Laboratory: up to about 3 days</td><td data-label="Antigen">15–30 minutes</td></tr>
        <tr><th scope="row">A negative result</th><td data-label="RT-PCR">Reliable at the time of testing</td><td data-label="Antigen">Does not rule out infection on its own</td></tr>
        <tr><th scope="row">Good for</th><td data-label="RT-PCR">Confirming a result, travel or procedure requirements</td><td data-label="Antigen">Quick checks with symptoms, repeated testing</td></tr>
      </tbody>
    </table>
  </div>

  <div class="split split--even mt-6">
    <section aria-labelledby="repeat">
      <p class="eyebrow"><span class="num">A</span> After a negative antigen test</p>
      <h2 id="repeat">Test again</h2>
      <p>A single negative antigen test can miss an infection. The US FDA guidance summarised by CDC is:</p>
      <ul>
        <li><strong>With symptoms:</strong> two negative tests taken 48 hours apart.</li>
        <li><strong>Without symptoms:</strong> three negative tests, each 48 hours apart.</li>
      </ul>
      <p>A molecular (RT-PCR) test can be used to confirm an antigen result.</p>
    </section>
    <section aria-labelledby="recent">
      <p class="eyebrow"><span class="num">B</span> After a recent infection</p>
      <h2 id="recent">Results can stay positive</h2>
      <p>Molecular tests can remain positive for up to 90 days after an infection, even after recovery. If you tested positive in the past 90 days, CDC advises using an antigen test to check for a new infection. Without symptoms in the first 30 days, testing is generally not recommended.</p>
      <p class="muted">Vitaliq offers only the two test types used to detect a current infection.</p>
    </section>
  </div>

  <section class="mt-6" aria-labelledby="sample">
    <p class="eyebrow"><span class="num">C</span> Giving a sample</p>
    <h2 id="sample">Before your collection</h2>
    <ol class="ledger">
      <li><span class="ledger__num">01</span><div><h3>Keep your reference ready</h3><p>The phlebotomist will confirm your name and reference before collecting the sample.</p></div></li>
      <li><span class="ledger__num">02</span><div><h3>Follow the collector’s instructions</h3><p>Some specimen types have preparation steps. The phlebotomist will tell you what applies to your test.</p></div></li>
      <li><span class="ledger__num">03</span><div><h3>Wear a mask and keep your distance</h3><p>For home collection, wait in a well-ventilated room if you can.</p></div></li>
      <li><span class="ledger__num">04</span><div><h3>Follow your result online</h3><p>You can see each step and the authorised result with your reference, or in your account.</p></div></li>
    </ol>
    <div class="cluster mt-5">
      <a class="btn btn--primary" href="<?= e(url('/test/new')) ?>">Request a test</a>
      <a class="btn btn--secondary" href="<?= e(url('/track')) ?>">Track an existing test</a>
    </div>
  </section>

  <?= $view->partial('sources', ['sources' => [
      ['CDC: Testing for COVID-19', 'https://www.cdc.gov/covid/testing/index.html', 'US Centers for Disease Control and Prevention', '10 Mar 2025'],
      ['WHO fact sheet: Coronavirus disease (COVID-19)', 'https://www.who.int/news-room/fact-sheets/detail/coronavirus-disease-(covid-19)', 'World Health Organization', '27 Nov 2025'],
  ]]) ?>
</div>
