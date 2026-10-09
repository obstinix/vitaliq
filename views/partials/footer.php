<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <?= $view->partial('wordmark', []) ?>
        <p class="mt-4">Health data, made meaningful. Public COVID-19 information and a testing workflow you can follow from request to result.</p>
      </div>
      <nav aria-label="Information">
        <h2>Information</h2>
        <ul>
          <li><a href="<?= e(url('/covid-19')) ?>">COVID-19 overview</a></li>
          <li><a href="<?= e(url('/symptoms')) ?>">Symptoms and warning signs</a></li>
          <li><a href="<?= e(url('/prevention')) ?>">Prevention</a></li>
          <li><a href="<?= e(url('/faq')) ?>">Questions</a></li>
        </ul>
      </nav>
      <nav aria-label="Testing">
        <h2>Testing</h2>
        <ul>
          <li><a href="<?= e(url('/testing')) ?>">How testing works</a></li>
          <li><a href="<?= e(url('/test/new')) ?>">Request a test</a></li>
          <li><a href="<?= e(url('/track')) ?>">Track a test</a></li>
          <li><a href="<?= e(url('/statistics')) ?>">Statistics</a></li>
        </ul>
      </nav>
      <nav aria-label="Vitaliq">
        <h2>Vitaliq</h2>
        <ul>
          <li><a href="<?= e(url('/announcements')) ?>">Announcements</a></li>
          <li><a href="<?= e(url('/community')) ?>">Community</a></li>
          <li><a href="<?= e(url('/contact')) ?>">Contact and help</a></li>
          <li><a href="<?= e(url('/privacy')) ?>">Your information</a></li>
        </ul>
      </nav>
    </div>
    <div class="footer-legal">
      <p><strong>Demonstration system.</strong> Vitaliq is a Software Testing and Quality Assurance project. It does not collect real samples or issue real medical results, and statistics shown for the platform come from its own database. In an emergency in India, call <strong>112</strong>.</p>
      <p>Health information on this site summarises the WHO and CDC pages it links to. It is not a substitute for advice from a qualified clinician.</p>
    </div>
  </div>
</footer>
