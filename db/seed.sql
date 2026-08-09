-- ---------------------------------------------------------------------------
-- Demo data.
--
-- Every row here is invented. The original project's database contained real
-- personal data (names, working email addresses, phone numbers, and unsalted
-- MD5 password hashes) belonging to third parties; none of it is reproduced in
-- this repository. See docs/schema-recovery.md.
--
-- Demo credentials:
--   admin@example.com / Admin!2345
--   alex@example.com  / Passw0rd!23   (and every other seeded user)
--
-- These are throwaway credentials for a local demo. Do not deploy this file.
-- ---------------------------------------------------------------------------

INSERT INTO roles (rid, name) VALUES
    (1, 'Admin'),
    (2, 'User');

INSERT INTO users (uid, name, email, password, phone, country, rid) VALUES
    (1, 'Demo Admin',  'admin@example.com', '$2y$10$XNYVMzmnzYBcpxKghX9BAuv4WgIXU79r/6KN8dYju2wIG9/kX5os.', '+44 20 7946 0100', 'United Kingdom', 1),
    (2, 'Alex Doe',    'alex@example.com',  '$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0101', 'United Kingdom', 2),
    (3, 'Sam Rivera',  'sam@example.com',   '$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0102', 'Ireland',        2),
    (4, 'Jordan Blake','jordan@example.com','$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0103', 'Canada',         2);

-- Stadiums. capacity_* values are small on purpose so the capacity limit is
-- easy to hit by hand when demoing; tests/BookingCapacityTest.php relies on
-- the Riverside Park numbers.
INSERT INTO stadium (sid, name, capacity_vip, capacity_platinum, capacity_gold, description) VALUES
    (1, 'Northgate Arena',   50, 100, 150, 'Home of the Northgate Rovers. Covered north and south stands.'),
    (2, 'Riverside Park',     2,   5,  10, 'Compact riverside ground. Deliberately tiny capacities for demo purposes.'),
    (3, 'Kingsfield Stadium',30,  60, 120, 'Renovated in 2019 with an expanded platinum tier.'),
    (4, 'Old Mill Ground',   40,  80, 100, 'The oldest ground in the league.');

INSERT INTO teams (tid, name, manager, sid, details) VALUES
    (1, 'Northgate Rovers',  'R. Calloway',  1, 'Founded 1904. Four-time league winners.'),
    (2, 'Riverside United',  'M. Okonkwo',   2, 'Promoted last season. Strong away record.'),
    (3, 'Kingsfield City',   'D. Fairbanks', 3, 'Known for a high-pressing style.'),
    (4, 'Old Mill Athletic', 'P. Basu',     4, 'League runners-up in 2023.');

INSERT INTO matches
    (mid, title, description, venue, match_date, kickoff_time, ptw, price_vip, price_platinum, price_gold, hometeam, awayteam)
VALUES
    (1, 'Northgate Rovers vs Riverside United', 'Opening fixture of the season.',        1, '2026-09-12', '15:00:00', 'Ellis, Vance, Duarte',   100.00, 50.00, 35.00, 1, 2),
    (2, 'Riverside Park derby',                 'Local derby. Expect a full house.',     2, '2026-09-19', '17:30:00', 'Okonkwo Jr., Sandoval',  110.00, 80.00, 50.00, 2, 3),
    (3, 'Kingsfield City vs Old Mill Athletic', 'Top-of-the-table clash.',               3, '2026-09-26', '20:00:00', 'Fairbanks, Renz, Aliyev',  70.00, 65.00, 28.00, 3, 4),
    (4, 'Old Mill Athletic vs Northgate Rovers','Cup quarter-final.',                    4, '2026-10-03', '19:45:00', 'Basu, Ellis',              85.00, 60.00, 40.00, 4, 1);

-- A couple of existing bookings so the "my tickets" page is not empty on a
-- fresh install, and so remaining-seat counts start at a non-trivial number.
INSERT INTO bookings (uid, mid, seat_tier, paid_amount) VALUES
    (2, 1, 'vip',      100.00),
    (3, 1, 'gold',      35.00),
    (2, 3, 'platinum',  65.00);
