<section class="section">
  <div class="wrap wrap--narrow">
    <h1>Developers</h1>
    <p class="lead">Vitaliq offers a small, versioned JSON API and read-only FHIR R4 resources. Keys are issued by an administrator.</p>
    <h2>Authentication</h2>
    <p>Send the key on every request as <code>Authorization: Bearer vq_…</code>. Each key has scopes and an expiry, and is limited to 120 requests a minute.</p>
    <ul>
      <?php foreach ($scopes as $k => $l): ?><li><code><?= e($k) ?></code>: <?= e($l) ?></li><?php endforeach; ?>
    </ul>
    <h2>Endpoints</h2>
    <ul>
      <li><code>GET /api/v1</code>: index, no key needed</li>
      <li><code>GET /api/v1/surveillance/summary?disease=dengue&amp;days=28&amp;state=KL</code>: tests, positives and positivity with a 95% interval, by state and by day</li>
      <li><code>GET /api/v1/facilities</code>: open facilities</li>
      <li><code>GET /api/v1/reports/verify/{code}</code>: check a report verification code</li>
      <li><code>GET /fhir/r4/metadata</code>: capability statement, no key needed</li>
      <li><code>GET /fhir/r4/Patient/{id}</code>, <code>/fhir/r4/Observation?patient={id}</code>, <code>/fhir/r4/DiagnosticReport?patient={id}</code> (and by id): <code>application/fhir+json</code></li>
    </ul>
    <h2>Errors</h2>
    <p>Errors return a status code with <code>{"error": {"status": 401, "message": "…"}}</code>; FHIR endpoints return an <code>OperationOutcome</code>. Every FHIR read is recorded in the audit log.</p>
    <p class="small">The FHIR endpoints are a read-only subset for integration testing. They have not been certified against a FHIR conformance programme.</p>
  </div>
</section>
