<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Sanitizer.php';
require_once __DIR__ . '/../utils/Jwt.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Utils\Jwt;

class AuthController
{
    private PDO $db;
    private array $config;
    private array $mailConfig;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config['app'] ?? $config;
        $this->mailConfig = $config['mail'] ?? [];
    }

    public function register(array $data): void
    {
        // 1. Validaciones básicas
        $missing = Validator::required($data, ['name', 'email', 'phone', 'password']);
        if ($missing) {
            Response::json(['success' => false, 'error' => 'Faltan campos requeridos'], 422);
        }

        $email = strtolower(Sanitizer::string($data['email']));
        $password = (string)$data['password'];

        if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            Response::json(['success' => false, 'error' => 'La contraseña requiere 8 caracteres, una mayúscula y un número'], 422);
        }

        $userModel = new User($this->db);
        if ($userModel->findByEmail($email)) {
            Response::json(['success' => false, 'error' => 'El email ya esta registrado'], 409);
        }

        // 2. Creación del usuario en Base de Datos
        $token = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $userModel->create([
            'name' => Sanitizer::string($data['name']),
            'email' => $email,
            'phone' => Sanitizer::string($data['phone']),
            'password_hash' => $passwordHash,
            'role' => 'USUARIO',
            'activation_token' => $token,
            'is_active' => 0 
        ]);

        try {
            require_once __DIR__ . '/../services/TelegramService.php';
            (new TelegramService())->sendMessage(
                '🆕 Nuevo usuario registrado: ' . Sanitizer::string($data['name']) . ' / ' . $email
            );
        } catch (\Throwable $e) {
            error_log('Telegram registro: ' . $e->getMessage());
        }

        // 3. Intento de envío de Email (Sin bloquear el registro si falla)
        $mailSent = false;
        try {
            // Solo intentamos enviar si la clase PHPMailer está cargada
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mailSent = $this->sendActivationEmail($email, $data['name'], $token);
            }
        } catch (\Throwable $e) {
            error_log("Error no crítico en envío de mail: " . $e->getMessage());
        }

        // 4. Generación de JWT (incluye CSRF para validar sin depender de sesión PHP entre orígenes)
        $csrf = bin2hex(random_bytes(32));
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'role' => 'USUARIO',
            'csrf' => $csrf,
            'iat' => time(),
            'exp' => time() + ($this->config['jwt_exp_minutes'] * 60),
        ];

        $jwtToken = Jwt::encode($payload, $this->config['jwt_secret']);

        // 5. LIMPIEZA CRÍTICA: Borra cualquier error o warning de PHP que se haya colado antes
        if (ob_get_length()) ob_clean();

        Response::json([
            'success' => true,
            'token' => $jwtToken,
            'csrf_token' => $csrf,
            'mail_sent' => $mailSent,
            'user' => [
                'id' => $userId,
                'name' => $data['name'],
                'email' => $email,
                'role' => 'USUARIO',
                'is_active' => 0
            ]
        ], 201);
    }

    private function sendActivationEmail($email, $name, $token) {
    // Forzamos la creación del log para saber si la función se ejecuta
    file_put_contents("mail_debug.log", "Iniciando envío para: $email \n", FILE_APPEND);

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; 
        $mail->isSMTP();
        $mail->Host       = $this->mailConfig['host'] ?? 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->mailConfig['username'] ?? ''; 
        $mail->Password   = $this->mailConfig['password'] ?? ''; 
        
        $encryption = strtolower($this->mailConfig['encryption'] ?? 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port       = (int)($this->mailConfig['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $logoPath = dirname(__DIR__, 2) . '/frontend/src/assets/logo.png';
        
        $fromEmail = $this->mailConfig['from_email'] ?? 'no-reply@libreria-gabi.com';
        $fromName = $this->mailConfig['from_name'] ?? 'Librería Gabi';
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->addEmbeddedImage($logoPath, 'logo_img');
        $mail->isHTML(true);
        $mail->Subject = 'Activa tu cuenta - Librería Gabi';
        $frontendUrl = rtrim($this->config['frontend_url'] ?? 'http://localhost:5173', '/');
        $url = "{$frontendUrl}/activate?token=$token";
        $mail->Body = "
            <div style='background-color: #f4f7f6; padding: 40px 10px; font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
                    
                    <div style='background-color: #1a233d; padding: 20px; text-align: center;'>
                        <img src='cid:logo_img' alt='Librería Gabi' style='height: 80px; width: auto; display: block; margin: 0 auto;'>
                    </div>

                    <div style='padding: 30px; text-align: center;'>
                        <h1 style='color: #1a233d; font-size: 24px; margin-top: 0;'>¡Hola, " . htmlspecialchars($name) . "!</h1>
                        <p style='color: #4a5568; font-size: 16px; line-height: 1.6;'>
                            Gracias por unirte a <strong>Librería Gabi</strong>. Estamos emocionados de tenerte con nosotros. 
                            Para empezar a explorar nuestro mundo de cuentos, solo necesitas activar tu cuenta.
                        </p>
                        
                        <div style='margin: 30px 0;'>
                            <a href='$url' style='background-color: #ff9f43; color: #ffffff; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 18px; display: inline-block; border: 1px solid #ff8c1a;'>
                                Activar mi cuenta
                            </a>
                        </div>

                        <p style='color: #a0aec0; font-size: 12px; margin-top: 40px;'>
                            Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                            <a href='$url' style='color: #ff9f43; text-decoration: underline;'>$url</a>
                        </p>
                    </div>

                    <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #edf2f7;'>
                        <p style='color: #718096; font-size: 12px; margin: 0;'>
                            © 2026 Librería Gabi - Mundo de cuentos. Todos los derechos reservados.
                        </p>
                    </div>
                </div>
            </div>
            ";
        $mail->AltBody = "Hola $name. Activa tu cuenta en este enlace: $url";
        $success = $mail->send();
        file_put_contents("mail_debug.log", "¡Correo enviado con éxito! \n", FILE_APPEND);
        return $success;

    } catch (Exception $e) {
        // Si entra aquí, Gmail dio error
        file_put_contents("mail_debug.log", "Error de PHPMailer: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        return false;
    } catch (\Throwable $t) {
        // Si entra aquí, es un error de PHP (ej. PHPMailer no instalado)
        file_put_contents("mail_debug.log", "Error FATAL de PHP: " . $t->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}


    public function login(array $data): void
    {
        $missing = Validator::required($data, ['email', 'password']);
        if ($missing) {
            Response::json(['error' => 'Missing fields'], 422);
        }

        $userModel = new User($this->db);
        $email = strtolower(Sanitizer::string((string)($data['email'] ?? '')));
        $plainPassword = (string)($data['password'] ?? '');
        $user = $userModel->findByEmail($email);
        if (!$user || !password_verify($plainPassword, (string)($user['password_hash'] ?? ''))) {
            Response::json(['error' => 'Invalid credentials'], 401);
        }

        $csrf = bin2hex(random_bytes(32));
        $payload = [
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'csrf' => $csrf,
            'iat' => time(),
            'exp' => time() + ($this->config['jwt_exp_minutes'] * 60),
            'iss' => $this->config['jwt_issuer'] ?? 'localhost',
        ];

        $token = Jwt::encode($payload, $this->config['jwt_secret']);

        Response::json([
            'token' => $token,
            'csrf_token' => $csrf,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => $user['is_active']
            ],
        ]);
    }

    /**
     * Nuevo par JWT + cabecera X-CSRF-TOKEN (sin rotar sesión PHP).
     */
    public function refreshCsrf(array $payload): void
    {
        $csrf = bin2hex(random_bytes(32));
        $newPayload = [
            'sub' => (int)($payload['sub'] ?? 0),
            'email' => (string)($payload['email'] ?? ''),
            'role' => (string)($payload['role'] ?? 'USUARIO'),
            'csrf' => $csrf,
            'iat' => time(),
            'exp' => time() + ((int)($this->config['jwt_exp_minutes'] ?? 60) * 60),
        ];
        if (!empty($payload['iss'])) {
            $newPayload['iss'] = $payload['iss'];
        }

        $token = Jwt::encode($newPayload, $this->config['jwt_secret']);
        Response::json([
            'csrf_token' => $csrf,
            'token' => $token,
        ]);
    }

   public function activate(array $data): void
{
    header('Content-Type: application/json');
    if (ob_get_level() > 0) ob_clean(); 
    ob_start();

    try {
        $token = $data['token'] ?? null;
        $userModel = new User($this->db);
        $user = $userModel->findByActivationToken($token);
        
        if (!$user) {
            throw new \Exception("Token inválido o ya utilizado.");
        }

        $userModel->activateUser($user['id']);
        $userData = $userModel->findById((int)$user['id']);

        $jwtSecret = $this->config['jwt_secret'] ?? 'tu_clave_secreta';
        $csrf = bin2hex(random_bytes(32));
        $tokenJwt = Jwt::encode([
            'sub' => (int)$userData['id'],
            'email' => (string)$userData['email'],
            'role' => (string)($userData['role'] ?? 'USUARIO'),
            'csrf' => $csrf,
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24),
        ], $jwtSecret);

        ob_clean(); 
        echo json_encode([
            'success' => true,
            'message' => '¡Cuenta activada!',
            'token'   => $tokenJwt,
            'csrf_token' => $csrf,
            'user'    => [
                'id'    => $userData['id'],
                'name'  => $userData['name'],
                'email' => $userData['email'],
                'role'  => $userData['role'] ?? 'USUARIO',
                'is_active' => 1,
            ]
        ]);
        exit;

    } catch (\Throwable $e) {
        if (ob_get_level() > 0) ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
}