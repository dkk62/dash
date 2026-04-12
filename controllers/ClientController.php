<?php
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/User.php';

if ($action === 'client_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id           = (int) ($_POST['id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $cycleType    = $_POST['cycle_type'] ?? 'monthly';
    $password     = trim($_POST['password'] ?? '');
    $processor0Id = !empty($_POST['processor0_id']) ? (int) $_POST['processor0_id'] : null;
    $processor1Id = !empty($_POST['processor1_id']) ? (int) $_POST['processor1_id'] : null;

    if ($name === '' || $email === '') {
        setFlash('danger', 'Name and email are required.');
        redirect('?action=clients');
    }

    if (!in_array($cycleType, ['monthly', 'yearly'])) {
        $cycleType = 'monthly';
    }

    try {
        if ($id > 0) {
            // Update existing - password is optional
            Client::update($id, $name, $email, $phone, $cycleType, $password ?: null, $processor0Id, $processor1Id);
            setFlash('success', 'Client updated.');
        } else {
            // Check if this email already has existing client records
            $isExistingEmail = Client::emailExists($email);

            // Create new - set default password
            $defaultPassword = 'Password#2026';
            $newClientId = Client::create($name, $email, $phone, $cycleType, $defaultPassword, $processor0Id, $processor1Id);

            // Send appropriate welcome email
            if ($isExistingEmail) {
                sendNewEntityEmail($name, $email);
            } else {
                sendClientWelcomeEmail($name, $email);
            }

            setFlash('success', 'Client created and welcome email sent.');
            redirect('?action=clients');
        }
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect('?action=clients');
}

/**
 * Send welcome email to a newly created client.
 */
function sendClientWelcomeEmail(string $name, string $email): void {
    $baseUrl  = 'https://dashboard.taxcheapo.com';
    $loginUrl = $baseUrl . '/?action=login';
    $resetUrl = $baseUrl . '/?action=forgot_password';

    $subject = 'Welcome to Tax Cheapo – Client Portal Access';
    $body    = "Hello {$name},\n\n"
             . "Welcome to the Tax Cheapo Client Portal—your account has been successfully created.\n\n"
             . "Login Email: {$email}\n\n"
             . "To get started, please set your password using the link below:\n"
             . "{$resetUrl}\n\n"
             . "Once your password is set, you can log in here:\n"
             . "{$loginUrl}\n\n"
             . "After logging in, you'll be prompted to complete a short onboarding form. "
             . "Please complete all sections so our team can begin working on your account without delay.\n\n"
             . "If you have any questions, feel free to reply to this email or contact us at info@taxcheapo.com.\n\n"
             . "Kind regards,\nTax Cheapo Team";

    $mailerPath = BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    if (!file_exists($mailerPath)) {
        return;
    }

    require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo('info@taxcheapo.com', SMTP_FROM_NAME);
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
    } catch (\Exception $e) {
        // Log but don't block client creation
        error_log('Welcome email failed for ' . $email . ': ' . $e->getMessage());
    }
}

/**
 * Send email when a new entity/organization is added for an existing client email.
 */
function sendNewEntityEmail(string $entityName, string $email): void {
    $baseUrl  = 'https://dashboard.taxcheapo.com';
    $loginUrl = $baseUrl . '/?action=login';

    $subject = 'New Organization Added - TaxCheapo Client Portal';
    $body    = "Hello,\n\n"
             . "A new organization has been added to your TaxCheapo Client Portal account:\n\n"
             . "Organization: {$entityName}\n\n"
             . "Please log in and complete the onboarding form for this organization. "
             . "You can switch between your entities on the onboarding page.\n\n"
             . "Login here:\n"
             . "{$loginUrl}\n\n"
             . "If you have any questions, please reply to this email or contact us at info@taxcheapo.com.\n\n"
             . "Regards,\nTaxCheapo Team";

    $mailerPath = BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    if (!file_exists($mailerPath)) {
        return;
    }

    require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo('info@taxcheapo.com', SMTP_FROM_NAME);
        $mail->addAddress($email, $entityName);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
    } catch (\Exception $e) {
        error_log('New entity email failed for ' . $email . ': ' . $e->getMessage());
    }
}

if ($action === 'client_resend_welcome' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $client = Client::find($id);
        if ($client) {
            $hasOtherClients = Client::emailExists($client['email'], $id);
            if ($hasOtherClients) {
                sendNewEntityEmail($client['name'], $client['email']);
            } else {
                sendClientWelcomeEmail($client['name'], $client['email']);
            }
            setFlash('success', 'Welcome email sent to ' . $client['email'] . '.');
        }
    }
    redirect('?action=clients');
}

if ($action === 'client_archive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    $archive = (int) ($_POST['archive'] ?? 1);
    if ($id > 0) {
        Client::setArchived($id, (bool) $archive);
        setFlash('success', $archive ? 'Client archived.' : 'Client unarchived.');
    }
    redirect('?action=clients');
}

if ($action === 'client_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        Client::delete($id);
        setFlash('success', 'Client deleted.');
    }
    redirect('?action=clients');
}

// List + edit form
$clients = Client::all();
$editClient = null;
if ($action === 'client_edit' && isset($_GET['id'])) {
    $editClient = Client::find((int) $_GET['id']);
}

// Fetch onboarding statuses for all clients
require_once BASE_PATH . '/models/OnboardingForm.php';
$onbDb = getDB();
$onbStmt = $onbDb->query("SELECT client_id, status FROM client_onboarding");
$onboardingStatuses = [];
while ($row = $onbStmt->fetch()) {
    $onboardingStatuses[(int)$row['client_id']] = $row['status'];
}

$processorUsers = User::byRoles(['processor0', 'processor1']);

include BASE_PATH . '/views/clients.php';
