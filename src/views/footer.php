</main>

<footer class="app-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="app-brand mb-3" href="<?= url('index.php') ?>">
                    <svg class="app-brand-mark" width="26" height="26" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/>
                        <path d="M13 5v3M13 11v2M13 16v3"/>
                    </svg>
                    <span>Stadium Booking</span>
                </a>
                <p class="text-muted small mb-0" style="max-width: 46ch;">
                    A third-year coursework project from 2021, found five years later and
                    rebuilt — because the admin panel had no access control and the booking
                    flow would happily oversell a stadium. Every fixture, club and ground
                    below is real; every account is invented.
                </p>
            </div>

            <div class="col-6 col-lg-3">
                <h2>Browse</h2>
                <ul>
                    <li><a href="<?= url('index.php') ?>">Home</a></li>
                    <li><a href="<?= url('fixtures.php') ?>">Fixtures</a></li>
                    <li><a href="<?= url('myticket.php') ?>">My tickets</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-4">
                <h2>The rebuild</h2>
                <ul>
                    <li>
                        <a href="https://github.com/Amunet98/stadium-booking" rel="noopener">Source on GitHub</a>
                    </li>
                    <li>
                        <a href="https://github.com/Amunet98/stadium-booking/blob/main/docs/SECURITY-FINDINGS.md" rel="noopener">
                            Twenty documented findings
                        </a>
                    </li>
                    <li>
                        <a href="https://www.bimeshpoudel.com.np" rel="noopener">More of my work</a>
                    </li>
                </ul>
                <?php /* Only outside production: on the public demo the credentials
                         are in the README, and printing them on every page invites
                         drive-by edits to the seed data. */ ?>
                <?php if ((getenv('APP_ENV') ?: 'development') !== 'production'): ?>
                    <p class="small text-muted mb-0">
                        Demo logins: <code>admin@example.com</code> / <code>Admin!2345</code>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="app-footer-base d-flex flex-wrap justify-content-between gap-2">
            <span>Online Stadium Booking &middot; Bimesh Poudel &amp; Aadarsh Gurung &middot; 2021, restored 2026</span>
            <span>Club badges and ground artwork drawn for this project. No crest is reproduced.</span>
        </div>
    </div>
</footer>

<script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/theme.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
