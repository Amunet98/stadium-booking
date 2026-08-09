-- ---------------------------------------------------------------------------
-- Online Stadium Booking — schema
--
-- The original 2021 project shipped no .sql file. This schema was reconstructed
-- from phpMyAdmin screenshots embedded in the project report, then given the
-- constraints the original never had. See docs/schema-recovery.md.
--
-- Changes from the recovered original are marked [NEW].
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS stadium;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- ---------------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------------
CREATE TABLE roles (
    rid  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(32)  NOT NULL,
    PRIMARY KEY (rid),
    UNIQUE KEY uq_roles_name (name)              -- [NEW]
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- users
--
-- [NEW] password column widened to 255 for bcrypt (was 32, sized for MD5).
-- [NEW] email made UNIQUE. The original checked for duplicates in PHP only,
--       which is racy: two concurrent signups could both pass the check.
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    uid        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120)  NULL,
    email      VARCHAR(190) NOT NULL,
    password   VARCHAR(255) NOT NULL,
    phone      VARCHAR(32)   NULL,
    country    VARCHAR(80)   NULL,
    rid        INT UNSIGNED NOT NULL DEFAULT 2,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- [NEW]
    PRIMARY KEY (uid),
    UNIQUE KEY uq_users_email (email),                            -- [NEW]
    KEY idx_users_rid (rid),
    CONSTRAINT fk_users_role                                      -- [NEW]
        FOREIGN KEY (rid) REFERENCES roles (rid)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- stadium
--
-- The original stored tier capacities in columns literally named a, b, c.
-- Renamed to say what they mean; the admin UI already labelled them
-- VIP / Platinum / Gold, and mapped two of them to the wrong column
-- (see docs/SECURITY-FINDINGS.md #10).
--
-- [NEW] CHECK constraints: capacity cannot be negative.
-- ---------------------------------------------------------------------------
CREATE TABLE stadium (
    sid               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(150) NOT NULL,          -- was sName
    capacity_vip      INT UNSIGNED NOT NULL DEFAULT 0,-- was a
    capacity_platinum INT UNSIGNED NOT NULL DEFAULT 0,-- was b
    capacity_gold     INT UNSIGNED NOT NULL DEFAULT 0,-- was c
    photo             VARCHAR(255) NULL,
    description       TEXT         NULL,
    PRIMARY KEY (sid),
    UNIQUE KEY uq_stadium_name (name)                 -- [NEW]
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- teams
-- ---------------------------------------------------------------------------
CREATE TABLE teams (
    tid     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name    VARCHAR(150) NOT NULL,          -- was tname
    manager VARCHAR(150) NULL,
    sid     INT UNSIGNED NULL,              -- home stadium
    details TEXT         NULL,
    photo   VARCHAR(255) NULL,              -- was photos
    PRIMARY KEY (tid),
    KEY idx_teams_sid (sid),
    CONSTRAINT fk_teams_stadium                                   -- [NEW]
        FOREIGN KEY (sid) REFERENCES stadium (sid)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- matches
--
-- [NEW] match_date DATE + kickoff_time TIME replace the original `time`
--       VARCHAR, which held strings like '01:00 UKT'. The original had no
--       date column at all, so fixtures could not be ordered or filtered
--       chronologically (see docs/SECURITY-FINDINGS.md #13).
-- [NEW] CHECK: a team cannot play itself.
-- [NEW] CHECK: prices cannot be negative.
-- ---------------------------------------------------------------------------
CREATE TABLE matches (
    mid             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    title           VARCHAR(200)   NOT NULL,
    description     TEXT           NULL,
    venue           INT UNSIGNED   NOT NULL,        -- -> stadium.sid
    match_date      DATE           NOT NULL,        -- [NEW]
    kickoff_time    TIME           NOT NULL,        -- [NEW]
    ptw             VARCHAR(255)   NULL,            -- "players to watch"
    price_vip       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,  -- was priceA
    price_platinum  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,  -- was priceB
    price_gold      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,  -- was priceC
    hometeam        INT UNSIGNED   NOT NULL,
    awayteam        INT UNSIGNED   NOT NULL,
    PRIMARY KEY (mid),
    KEY idx_matches_venue (venue),
    KEY idx_matches_date (match_date),                            -- [NEW]
    KEY idx_matches_hometeam (hometeam),
    KEY idx_matches_awayteam (awayteam),
    CONSTRAINT fk_matches_venue                                   -- [NEW]
        FOREIGN KEY (venue) REFERENCES stadium (sid)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_hometeam                                -- [NEW]
        FOREIGN KEY (hometeam) REFERENCES teams (tid)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_awayteam                                -- [NEW]
        FOREIGN KEY (awayteam) REFERENCES teams (tid)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_matches_distinct_teams                         -- [NEW]
        CHECK (hometeam <> awayteam),
    CONSTRAINT chk_matches_prices_non_negative                    -- [NEW]
        CHECK (price_vip >= 0 AND price_platinum >= 0 AND price_gold >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- bookings
--
-- The original `seattype` column held two incompatible vocabularies at once
-- ('a' and 'priceA' both appear in the recovered data) because the write path
-- changed and the read path did not. seat_tier now has a CHECK constraint so
-- the database refuses any value outside the agreed vocabulary.
--
-- [NEW] UNIQUE (mid, uid, seat_tier): a user cannot double-book the same tier
--       for the same fixture. This is a backstop; capacity is enforced in a
--       transaction (see src/config/booking.php).
-- ---------------------------------------------------------------------------
CREATE TABLE bookings (
    bid         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    uid         INT UNSIGNED  NOT NULL,
    mid         INT UNSIGNED  NOT NULL,
    seat_tier   VARCHAR(16)   NOT NULL,        -- was seattype
    paid_amount DECIMAL(10,2) NOT NULL,        -- was paidAmount
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP, -- was timestamp
    PRIMARY KEY (bid),
    KEY idx_bookings_mid (mid),                                   -- [NEW]
    KEY idx_bookings_uid (uid),                                   -- [NEW]
    KEY idx_bookings_capacity (mid, seat_tier),                   -- [NEW] serves the capacity count
    UNIQUE KEY uq_bookings_user_match_tier (mid, uid, seat_tier), -- [NEW]
    CONSTRAINT fk_bookings_user                                   -- [NEW]
        FOREIGN KEY (uid) REFERENCES users (uid)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_bookings_match                                  -- [NEW]
        FOREIGN KEY (mid) REFERENCES matches (mid)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_bookings_seat_tier                             -- [NEW]
        CHECK (seat_tier IN ('vip', 'platinum', 'gold')),
    CONSTRAINT chk_bookings_paid_amount                           -- [NEW]
        CHECK (paid_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
