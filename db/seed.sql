-- ---------------------------------------------------------------------------
-- Demo data.
--
-- The clubs, grounds and managers below are real; the *people* are not. The
-- original project's database contained real personal data (names, working
-- email addresses, phone numbers, and unsalted MD5 password hashes) belonging
-- to third parties; none of it is reproduced in this repository, and every
-- account here is invented. See docs/schema-recovery.md.
--
-- Managers are as of the 2025-26 season. Football moves faster than this
-- repository does, so treat a stale name as dated rather than wrong.
--
-- Demo credentials:
--   admin@example.com / Admin!2345
--   alex@example.com  / Passw0rd!23   (and every other seeded user)
--
-- These are throwaway credentials for a local demo. Do not deploy this file.
-- ---------------------------------------------------------------------------

-- Required here as well as in schema.sql, not redundantly: MySQL's
-- docker-entrypoint-initdb.d runs each file through a separate mysql client
-- invocation, so schema.sql's SET NAMES does not carry over. Without this the
-- client defaults to latin1, reads this file's UTF-8 bytes one at a time and
-- re-encodes each as UTF-8 — storing 'Ødegaard' as 'Ã˜degaard' and
-- 'Rúben' as 'RÃºben'. The database is fine; the import is what corrupts it.
SET NAMES utf8mb4;

INSERT INTO roles (rid, name) VALUES
    (1, 'Admin'),
    (2, 'User');

INSERT INTO users (uid, name, email, password, phone, country, rid) VALUES
    (1, 'Demo Admin',  'admin@example.com', '$2y$10$XNYVMzmnzYBcpxKghX9BAuv4WgIXU79r/6KN8dYju2wIG9/kX5os.', '+44 20 7946 0100', 'United Kingdom', 1),
    (2, 'Alex Doe',    'alex@example.com',  '$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0101', 'United Kingdom', 2),
    (3, 'Sam Rivera',  'sam@example.com',   '$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0102', 'Ireland',        2),
    (4, 'Jordan Blake','jordan@example.com','$2y$10$XYpUtM1M9v3Jr7ETgx9Mc.WCWrJWLPsK0r6zXYpTcey1nfroUN31a', '+44 20 7946 0103', 'Canada',         2);

-- ---------------------------------------------------------------------------
-- Grounds.
--
-- capacity_vip / capacity_platinum / capacity_gold are the allocation released
-- to *this demo*, not the size of the ground — the real figure is in the
-- description. They are deliberately small so the capacity limit can be
-- reached by hand in a minute rather than by scripting 60,000 bookings, and
-- tests/verify.sh relies on Anfield (sid 2) offering exactly 2 VIP seats.
-- ---------------------------------------------------------------------------
INSERT INTO stadium (sid, name, capacity_vip, capacity_platinum, capacity_gold, photo, description) VALUES
    (1, 'Emirates Stadium', 40, 120, 200, 'venues/emirates.svg',      'Holloway, north London. Arsenal''s home since 2006; capacity 60,704.'),
    (2, 'Anfield',           2,   5,  10, 'venues/anfield.svg',       'Anfield Road, Liverpool. Home to Liverpool since 1892; capacity 61,276.'),
    (3, 'Etihad Stadium',   30,  90, 160, 'venues/etihad.svg',        'Eastlands, Manchester. Built for the 2002 Commonwealth Games; capacity 53,400.'),
    (4, 'Old Trafford',     45, 130, 220, 'venues/old-trafford.svg',  'Trafford, Greater Manchester. The largest club ground in England; capacity 74,310.'),
    (5, 'St James'' Park',  25,  80, 140, 'venues/st-james-park.svg', 'Newcastle upon Tyne. In continuous use since 1892; capacity 52,305.'),
    (6, 'Villa Park',       20,  70, 120, 'venues/villa-park.svg',    'Aston, Birmingham. Host of more FA Cup semi-finals than any other ground; capacity 42,918.');

INSERT INTO teams (tid, name, manager, sid, photo, details) VALUES
    (1, 'Arsenal',           'Mikel Arteta',   1, 'badges/arsenal.svg',     'Founded 1886. Thirteen league titles and a record fourteen FA Cups.'),
    (2, 'Liverpool',         'Arne Slot',      2, 'badges/liverpool.svg',   'Founded 1892. Six European Cups, the most of any English club.'),
    (3, 'Manchester City',   'Pep Guardiola',  3, 'badges/man-city.svg',    'Founded 1880. Six league titles in the seven seasons to 2024.'),
    (4, 'Manchester United', 'Rúben Amorim',   4, 'badges/man-utd.svg',     'Founded 1878. Twenty league titles, a domestic record.'),
    (5, 'Newcastle United',  'Eddie Howe',     5, 'badges/newcastle.svg',   'Founded 1892. Ended a 70-year trophy wait with the 2025 League Cup.'),
    (6, 'Aston Villa',       'Unai Emery',     6, 'badges/aston-villa.svg', 'Founded 1874. A founder member of the Football League.');

-- Six Saturdays across September and October 2026. Matches 1-4 keep their mid
-- because tests/verify.sh addresses fixtures by id: it books VIP seats at
-- match 2 (Anfield, 2 available) and asserts match 1 is unaffected, which only
-- proves anything while the two are at different grounds.
INSERT INTO matches
    (mid, title, description, venue, match_date, kickoff_time, ptw, price_vip, price_platinum, price_gold, hometeam, awayteam)
VALUES
    (1, 'Arsenal vs Manchester City',           'Opening weekend, and probably the title decided in miniature.', 1, '2026-09-12', '17:30:00', 'Saka, Ødegaard, Haaland',    295.00, 110.00, 55.00, 1, 3),
    (2, 'Liverpool vs Manchester United',       'The oldest rivalry in the English game. Two VIP seats left.',   2, '2026-09-19', '16:30:00', 'Salah, Szoboszlai, Fernandes', 275.00, 105.00, 52.00, 2, 4),
    (3, 'Manchester City vs Newcastle United',  'Newcastle have taken points here in each of the last two.',     3, '2026-09-26', '15:00:00', 'Foden, Rodri, Tonali',       225.00,  85.00, 45.00, 3, 5),
    (4, 'Manchester United vs Aston Villa',     'Evening kick-off under the Old Trafford lights.',               4, '2026-10-03', '20:00:00', 'Fernandes, Mbeumo, Watkins', 240.00,  90.00, 48.00, 4, 6),
    (5, 'Newcastle United vs Arsenal',          'St James'' Park on a Saturday afternoon needs no introduction.',5, '2026-10-17', '15:00:00', 'Gordon, Tonali, Saka',       210.00,  80.00, 42.00, 5, 1),
    (6, 'Aston Villa vs Liverpool',             'Villa Park closes the October run.',                            6, '2026-10-24', '14:00:00', 'Watkins, Rogers, Salah',     195.00,  75.00, 38.00, 6, 2);

-- A couple of existing bookings so the "my tickets" page is not empty on a
-- fresh install, and so remaining-seat counts start at a non-trivial number.
-- Deliberately none at match 2: the capacity assertions in tests/verify.sh
-- start from a full VIP tier there and fill it themselves.
INSERT INTO bookings (uid, mid, seat_tier, paid_amount) VALUES
    (2, 1, 'vip',      295.00),
    (3, 1, 'gold',      55.00),
    (2, 3, 'platinum',  85.00);
