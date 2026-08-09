# `legacy/` — the 2021 code, as submitted

This directory is the original coursework, preserved so the restoration can be
read as a diff rather than taken on trust. **It is not the running application**
— see `src/` for that.

Do not fix anything in here. The bugs are the point; they are catalogued in
[../docs/SECURITY-FINDINGS.md](../docs/SECURITY-FINDINGS.md).

Two changes were made to this copy, and only these two:

1. The credit line in `footer.php` originally named three people unconnected to
   this repository, and has been replaced.
2. Bootstrap `.map` source maps (2.1 MB of generated build artifacts) were
   deleted. No hand-written code was touched.

This code does not run as-is on Linux or macOS — `bookingprocess.php` requires
`inc\connect.php` with a Windows path separator, which is fatal on any other
platform. It expected PHP 7 and MySQL under XAMPP, with a database named
`booking` that no longer exists and shipped no schema file.
