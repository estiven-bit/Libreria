<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * MailService mínimo basado en PHPMailer 6.x.
 *
 * Lee la config SMTP del entorno (.env). Si MAIL_HOST está vacío, isConfigured()
 * devuelve false y el caller debe usar una alternativa (en Pruebas, mostrar
 * el contenido en pantalla en vez de enviar).
 *
 * Solo expone helpers para los emails que el IdP necesita ahora mismo
 * (password reset). En el futuro: verificación de email en registro,
 * confirmación de cambio de email, alertas de login desde nueva ubicación...
 */
class MailService
{
    public static function isConfigured(): bool
    {
        return trim((string)(env('MAIL_HOST') ?: '')) !== ''
            && trim((string)(env('MAIL_USERNAME') ?: '')) !== ''
            && trim((string)(env('MAIL_PASSWORD') ?: '')) !== '';
    }

    /**
     * Envía un email transaccional. Devuelve true si llegó al servidor SMTP,
     * false si hubo error (queda en el log de errores).
     */
    public static function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (!self::isConfigured()) {
            error_log('[MailService] no enviado: SMTP sin configurar (MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD)');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string)env('MAIL_HOST');
            $mail->Port = (int)(env('MAIL_PORT') ?: 465);
            $mail->SMTPAuth = true;
            $mail->Username = (string)env('MAIL_USERNAME');
            $mail->Password = (string)env('MAIL_PASSWORD');

            $encryption = strtolower((string)(env('MAIL_ENCRYPTION') ?: 'ssl'));
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->CharSet = 'UTF-8';

            $fromEmail = (string)(env('MAIL_FROM') ?: env('MAIL_USERNAME'));
            $fromName = (string)(env('MAIL_FROM_NAME') ?: 'Librería Gabi');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[MailService] SMTP error a ' . $toEmail . ': ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Email transaccional: verificación de email tras registro. El usuario
     * acaba de pedir cuenta nueva; vive en pending_registrations hasta clic.
     */
    public static function sendVerifyEmail(string $toEmail, string $verifyUrl, string $name): bool
    {
        $urlEsc = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">¡Bienvenido, {$nameEsc}!</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Para terminar de crear tu cuenta de Librería Gabi, confirma tu email haciendo click
      en el botón. El enlace caduca en 24 horas.
    </p>
    <p style="text-align:center;margin:24px 0;">
      <a href="{$urlEsc}" style="display:inline-block;padding:12px 22px;background:#1a2d4e;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">
        Confirmar email
      </a>
    </p>
    <p style="margin:0;color:#5a6478;font-size:.85rem;">
      Si no has solicitado esta cuenta, ignora este email — no se creará nada.
    </p>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Si el botón no funciona, copia este enlace en tu navegador:<br>
      <span style="word-break:break-all;color:#5a6478;">{$urlEsc}</span>
    </p>
  </div>
</body></html>
HTML;
        $text = "¡Bienvenido, {$name}!\n\nConfirma tu email para activar tu cuenta de Librería Gabi (24 horas de validez):\n{$verifyUrl}\n\nSi no fuiste tú, ignora este email.";
        return self::send($toEmail, 'Confirma tu cuenta — Librería Gabi', $html, $text);
    }

    /**
     * Email transaccional: confirmación de cambio de email. Se envía al NUEVO
     * email — al hacer clic, el IdP aplica el cambio sobre users.email.
     */
    public static function sendEmailChangeConfirm(string $toEmail, string $confirmUrl, string $name): bool
    {
        $urlEsc = htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">Confirma tu nuevo email</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Hola {$nameEsc}, hemos recibido una petición para cambiar el email de tu
      cuenta de Librería Gabi a esta dirección. Confirma haciendo clic en el botón.
      El enlace caduca en 1 hora.
    </p>
    <p style="text-align:center;margin:24px 0;">
      <a href="{$urlEsc}" style="display:inline-block;padding:12px 22px;background:#1a2d4e;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">
        Confirmar nuevo email
      </a>
    </p>
    <p style="margin:0;color:#5a6478;font-size:.85rem;">
      Si no has sido tú, ignora este email. Hasta que confirmes, tu email
      seguirá siendo el actual.
    </p>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Si el botón no funciona, copia este enlace:<br>
      <span style="word-break:break-all;color:#5a6478;">{$urlEsc}</span>
    </p>
  </div>
</body></html>
HTML;
        $text = "Hola {$name},\n\nConfirma tu nuevo email para tu cuenta de Librería Gabi (1 hora de validez):\n{$confirmUrl}";
        return self::send($toEmail, 'Confirma tu nuevo email — Librería Gabi', $html, $text);
    }

    /**
     * Email transaccional: alerta de seguridad — login desde nuevo dispositivo
     * o nueva ubicación. Se envía al email actual del usuario.
     */
    public static function sendNewDeviceAlert(string $toEmail, string $name, string $device, string $location, string $when, string $confirmUrl, string $denyUrl): bool
    {
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $deviceEsc = htmlspecialchars($device, ENT_QUOTES, 'UTF-8');
        $locationEsc = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $whenEsc = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
        $confirmEsc = htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8');
        $denyEsc = htmlspecialchars($denyUrl, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">Nuevo inicio de sesión detectado</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Hola {$nameEsc}, alguien ha iniciado sesión en tu cuenta de Librería Gabi desde
      un dispositivo o ubicación nueva. ¿Has sido tú?
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:.9rem;color:#1a2d4e;background:#faf8f3;border-radius:8px;margin-bottom:20px;">
      <tr><td style="padding:.6rem .8rem;color:#5a6478;width:120px;">Dispositivo</td><td style="padding:.6rem .8rem;font-weight:600;">{$deviceEsc}</td></tr>
      <tr><td style="padding:.6rem .8rem;color:#5a6478;">Ubicación</td><td style="padding:.6rem .8rem;font-weight:600;">{$locationEsc}</td></tr>
      <tr><td style="padding:.6rem .8rem;color:#5a6478;">Fecha</td><td style="padding:.6rem .8rem;font-weight:600;">{$whenEsc}</td></tr>
    </table>
    <table style="width:100%;border-collapse:collapse;">
      <tr>
        <td style="padding:0 4px 0 0;width:50%;">
          <a href="{$confirmEsc}" style="display:block;text-align:center;padding:12px;background:#fff;color:#1a2d4e;border:1.5px solid #1a2d4e;border-radius:10px;text-decoration:none;font-weight:600;">
            ✓ Sí, fui yo
          </a>
        </td>
        <td style="padding:0 0 0 4px;width:50%;">
          <a href="{$denyEsc}" style="display:block;text-align:center;padding:12px;background:#b91c1c;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;">
            ✗ No, no he sido yo
          </a>
        </td>
      </tr>
    </table>
    <p style="margin:16px 0 0;color:#5a6478;font-size:.85rem;line-height:1.45;">
      Si <strong>no fuiste tú</strong>, haz clic en "No, no he sido yo" — cerraremos
      esa sesión inmediatamente y te llevaremos a cambiar tu contraseña.
    </p>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Email automático de seguridad. Los enlaces caducan en 24 horas.
    </p>
  </div>
</body></html>
HTML;
        $text = "Hola {$name},\n\nNuevo inicio de sesión en tu cuenta de Librería Gabi:\n· Dispositivo: {$device}\n· Ubicación: {$location}\n· Fecha: {$when}\n\n¿Fuiste tú? Sí: {$confirmUrl}\n¿No fuiste tú? Cierra la sesión y cambia tu contraseña: {$denyUrl}";
        return self::send($toEmail, 'Nuevo inicio de sesión — Librería Gabi', $html, $text);
    }

    /**
     * Email transaccional: notificación al email ACTUAL cuando se solicita un
     * cambio. Si el usuario reconoce la acción → ignora. Si NO fue él, clica
     * el botón "Cancelar y proteger mi cuenta" → revoca el cambio, mata todas
     * las sesiones y obliga a cambio de contraseña.
     */
    public static function sendEmailChangeNotice(string $toEmail, string $name, string $newEmail, string $denyUrl): bool
    {
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $newEsc = htmlspecialchars($newEmail, ENT_QUOTES, 'UTF-8');
        $denyEsc = htmlspecialchars($denyUrl, ENT_QUOTES, 'UTF-8');
        $when = date('d/m/Y H:i');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">Cambio de email solicitado</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Hola {$nameEsc}, el {$when} se ha pedido cambiar el email de tu cuenta
      de Librería Gabi a <strong>{$newEsc}</strong>.
    </p>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Si <strong>fuiste tú</strong>, ignora este correo — el cambio se aplicará
      cuando confirmes desde {$newEsc}.
    </p>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;padding:14px 16px;border-radius:10px;font-size:.9rem;margin-top:16px;">
      <strong>¿No fuiste tú?</strong><br>
      Tu cuenta podría estar comprometida. Cancela el cambio y resetea tu
      contraseña ahora:
      <p style="text-align:center;margin:14px 0 4px;">
        <a href="{$denyEsc}" style="display:inline-block;padding:11px 20px;background:#b91c1c;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">
          Cancelar y proteger mi cuenta
        </a>
      </p>
    </div>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Email automático de seguridad. El enlace caduca en 1 hora.
    </p>
  </div>
</body></html>
HTML;
        $text = "Hola {$name},\n\nSe ha solicitado cambiar el email de tu cuenta de Librería Gabi a {$newEmail} el {$when}.\n\nSi no fuiste tú, cancela el cambio y resetea tu contraseña inmediatamente:\n{$denyUrl}";
        return self::send($toEmail, 'Solicitud de cambio de email — Librería Gabi', $html, $text);
    }

    /**
     * Email transaccional: alerta de seguridad — la contraseña ha cambiado.
     * Se envía al email ACTUAL del usuario justo después de aplicar el cambio.
     */
    public static function sendPasswordChangedAlert(string $toEmail, string $name): bool
    {
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $when = date('d/m/Y H:i');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">Tu contraseña ha cambiado</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Hola {$nameEsc}, la contraseña de tu cuenta de Librería Gabi se ha cambiado
      el {$when}. Si has sido tú, no necesitas hacer nada.
    </p>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;padding:12px 16px;border-radius:8px;font-size:.9rem;">
      <strong>¿No has sido tú?</strong>
      Recupera tu cuenta inmediatamente desde "¿Olvidaste tu contraseña?"
      e informa al equipo de Librería Gabi.
    </div>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Este es un email automático de seguridad. No respondas.
    </p>
  </div>
</body></html>
HTML;
        $text = "Hola {$name},\n\nLa contraseña de tu cuenta de Librería Gabi se ha cambiado el {$when}. Si no fuiste tú, recupera tu cuenta inmediatamente.";
        return self::send($toEmail, 'Tu contraseña ha cambiado — Librería Gabi', $html, $text);
    }

    /**
     * Email transaccional concreto: enlace de recuperación de contraseña.
     */
    public static function sendPasswordReset(string $toEmail, string $resetUrl): bool
    {
        $urlEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html><body style="font-family:system-ui,sans-serif;color:#1a2d4e;background:#faf8f3;padding:32px;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #e4e6ec;border-radius:14px;padding:32px;">
    <h1 style="margin:0 0 12px;font-size:1.25rem;color:#1a2d4e;">Recuperar contraseña</h1>
    <p style="margin:0 0 16px;color:#5a6478;line-height:1.5;">
      Hemos recibido una petición para restablecer la contraseña de tu cuenta de Librería Gabi.
      Haz click en el botón para crear una nueva contraseña. El enlace caduca en 1 hora.
    </p>
    <p style="text-align:center;margin:24px 0;">
      <a href="{$urlEsc}" style="display:inline-block;padding:12px 22px;background:#1a2d4e;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">
        Cambiar contraseña
      </a>
    </p>
    <p style="margin:0;color:#5a6478;font-size:.85rem;">
      Si no has sido tú, ignora este email — tu contraseña no cambiará.
    </p>
    <hr style="border:0;border-top:1px solid #e4e6ec;margin:24px 0;">
    <p style="margin:0;color:#a3acbf;font-size:.75rem;">
      Si el botón no funciona, copia este enlace en tu navegador:<br>
      <span style="word-break:break-all;color:#5a6478;">{$urlEsc}</span>
    </p>
  </div>
</body></html>
HTML;
        $text = "Recuperar contraseña\n\nUsa este enlace para crear una nueva contraseña (1 hora de validez):\n{$resetUrl}\n\nSi no has sido tú, ignora este email.";
        return self::send($toEmail, 'Recupera tu contraseña — Librería Gabi', $html, $text);
    }
}
