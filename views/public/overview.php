<header class="page-head">
  <div class="wrap">
    <p class="eyebrow"><span class="num">Guide</span> COVID-19 overview</p>
    <h1>What COVID-19 is, and how it spreads.</h1>
    <p class="lede">A short, sourced summary for people deciding whether to test, and for anyone caring for someone at higher risk.</p>
  </div>
</header>

<div class="wrap section">
  <div class="split split--wide-left">
    <article class="prose">
      <h2>The disease</h2>
      <p>COVID-19 is the illness caused by the SARS-CoV-2 virus. WHO notes that most people recover completely without needing treatment, but some develop severe illness. A minority go on to have symptoms that last for months, known as post COVID-19 condition or long COVID.</p>

      <h2>How it spreads</h2>
      <p>The virus spreads mainly through infectious respiratory particles released when an infected person breathes, talks, coughs or sneezes. The risk is higher in close contact and in shared indoor spaces, especially poorly ventilated ones. It can also spread when people touch a contaminated surface and then their eyes, nose or mouth.</p>
      <p>People can pass the virus on before they have symptoms, and some infected people never develop symptoms at all. That is why testing matters even when someone feels well after a known exposure.</p>

      <h2>Who is at higher risk</h2>
      <p>WHO identifies these groups as more likely to become seriously ill:</p>
      <ul>
        <li>older adults, and people who have not been vaccinated;</li>
        <li>people with high blood pressure, diabetes or obesity;</li>
        <li>people with chronic lung, heart, liver or kidney disease, or cancer;</li>
        <li>people with weakened immune systems, including people living with HIV;</li>
        <li>people who are pregnant.</li>
      </ul>
      <p>Health and care workers are more likely to be exposed because of their work.</p>

      <h2>Treatment and vaccines</h2>
      <p>Treatment exists, but most mild cases do not need it. Clinicians decide on treatment based on how severe the illness is and the person’s individual risk. Vaccines protect well against severe disease and death, and WHO recommends vaccination particularly for people at higher risk and for health workers. Ask a clinician which vaccine schedule applies to you.</p>

      <h2>Where the pandemic stands</h2>
      <p>WHO declared a Public Health Emergency of International Concern on 30 January 2020 and ended it on 5 May 2023, describing COVID-19 as an established and ongoing health issue. The virus still circulates throughout the year.</p>
    </article>
    <aside class="stack">
      <div class="callout callout--urgent">
        <h3>Seek care immediately</h3>
        <p>For severe breathing difficulty, persistent chest pain or pressure, confusion, or loss of speech or movement. In India, call <strong>112</strong>.</p>
      </div>
      <div class="callout">
        <h3>Next steps</h3>
        <p><a href="<?= e(url('/symptoms')) ?>">Check symptoms</a>, read about <a href="<?= e(url('/testing')) ?>">which test to choose</a>, or <a href="<?= e(url('/test/new')) ?>">request a test</a>.</p>
      </div>
    </aside>
  </div>

  <?= $view->partial('sources', ['sources' => [
      ['WHO fact sheet: Coronavirus disease (COVID-19)', 'https://www.who.int/news-room/fact-sheets/detail/coronavirus-disease-(covid-19)', 'World Health Organization', '27 Nov 2025'],
      ['Statement on the fifteenth meeting of the IHR Emergency Committee on COVID-19', 'https://www.who.int/news/item/05-05-2023-statement-on-the-fifteenth-meeting-of-the-international-health-regulations-(2005)-emergency-committee-regarding-the-coronavirus-disease-(covid-19)-pandemic', 'World Health Organization', '5 May 2023'],
      ['Statement on the second meeting of the IHR Emergency Committee on 2019-nCoV', 'https://www.who.int/news/item/30-01-2020-statement-on-the-second-meeting-of-the-international-health-regulations-(2005)-emergency-committee-regarding-the-outbreak-of-novel-coronavirus-(2019-ncov)', 'World Health Organization', '30 Jan 2020'],
  ]]) ?>
</div>
