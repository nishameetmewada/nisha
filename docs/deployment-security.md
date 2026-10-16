# A browser warns that the deployed site is dangerous

A **Dangerous site** warning is different from “Your connection is not private”
(a certificate problem) or an uncommon-download warning. Enabling SSL alone does
not clear a site reputation or malicious-content warning. Do not disable browser
Safe Browsing or tell students to bypass a warning.

A passing local application test or a clean source checkout does not establish
that a live hosting account contains only those same files. Check the deployed
site and the specific security provider's report before calling it safe.

1. Run a complete malware scan through the hosting provider's tools, or ask its
   support team to scan the account and identify any unwanted files, scripts or
   redirects. Inspect the whole hosting account, not only the homepage.
2. Check the affected URL in Google's official Safe Browsing site-status page:
   https://transparencyreport.google.com/safe-browsing/search
3. Verify the site in Google Search Console and inspect **Security issues** for
   detected problems and affected URLs:
   https://search.google.com/search-console/security-issues
4. Remove confirmed unwanted content, restore clean project files, and secure
   the hosting access that allowed any compromise. Preserve the private database
   configuration and do not publish credentials or database backups.
5. When the stated issue is fixed and the site is verified clean, request a
   review through Search Console. An incorrect classification can be reported
   through Google's official reporting process after those checks. Browser
   clearance is not immediate and cannot be guaranteed by uploading a new ZIP.

Changing domains to avoid a warning does not address a compromised deployment.
New website features and browser-security clearance are separate tasks.
