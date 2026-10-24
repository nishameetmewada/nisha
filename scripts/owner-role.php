<?php
declare(strict_types=1);
// Private owner bootstrap. Direct HTTP execution is deliberately unavailable.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
if ($argc !== 3 || !in_array($argv[1],['grant-admin','revoke-admin'],true) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',$argv[2])) {
    fwrite(STDERR,"Usage: php scripts/owner-role.php grant-admin ACCOUNT_ID\n       php scripts/owner-role.php revoke-admin ACCOUNT_ID\nRun privately with SSH from your website directory. No account is created by this command.\n");exit(1);
}
try {
    require_once dirname(__DIR__).'/includes/bootstrap.php';
    $id=(int)$argv[2]; $q=db()->prepare('SELECT id FROM users WHERE id=?');$q->execute([$id]);
    if (!$q->fetchColumn()) throw new RuntimeException('Choose an existing registered account ID.');
    if ($argv[1]==='grant-admin') {
        $q=db()->prepare("INSERT INTO user_roles(user_id,role,assigned_by) VALUES (?,'admin',NULL) ON DUPLICATE KEY UPDATE role='admin',assigned_by=NULL");$q->execute([$id]);
        echo "Owner role granted to the selected existing account. Sign in again and open admin.php.\n";
    } else {
        $q=db()->prepare("DELETE FROM user_roles WHERE user_id=? AND role='admin'");$q->execute([$id]);
        echo "Database owner role removed. Also remove this account ID from private admin_user_ids if it was allowlisted there.\n";
    }
    session_write_close();
} catch (Throwable $exception) {
    // Never print SQL, private configuration, account names or credentials.
    fwrite(STDERR,"Owner setup could not complete. Check the selected account ID, private database configuration and teacher migration.\n");exit(1);
}
