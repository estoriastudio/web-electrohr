<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Bienvenido al Portal de Proveedores ElectroHR</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f5f7fa; color: #252b3b; font-family: Arial, sans-serif; line-height: 1.5;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-collapse: collapse;">
		<tr>
			<td style="padding: 32px;">
				<h1 style="margin: 0 0 20px; font-size: 24px;">Bienvenido al Portal de Proveedores ElectroHR</h1>

				<p>Hola{{ $supplier->commercial_name || $supplier->rfc_name ? ', ' . ($supplier->commercial_name ?? $supplier->rfc_name) : '' }}.</p>

				<p>El equipo ha habilitado su acceso al Portal de Proveedores. Utilice las siguientes credenciales para ingresar:</p>

				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #d9dee7; border-collapse: collapse;">
					<tr>
						<td style="padding: 12px; border-bottom: 1px solid #d9dee7; font-weight: bold; width: 35%;">Correo de acceso</td>
						<td style="padding: 12px; border-bottom: 1px solid #d9dee7;">{{ $portalEmail }}</td>
					</tr>
					<tr>
						<td style="padding: 12px; font-weight: bold;">Contraseña</td>
						<td style="padding: 12px;">{{ $portalPassword }}</td>
					</tr>
				</table>

				<p style="margin: 24px 0;">
					<a href="{{ $portalUrl }}" style="display: inline-block; padding: 12px 20px; background-color: #405189; color: #ffffff; text-decoration: none;">Acceder al portal</a>
				</p>

				<p>También puede acceder directamente en <a href="{{ $portalUrl }}">electrohr.app</a>.</p>
				<p>Por seguridad, puede cambiar su contraseña en cualquier momento desde el menú <strong>Configuraciones</strong> después de iniciar sesión.</p>

				<p style="margin-top: 28px;">Saludos,<br>Equipo ElectroHR</p>
			</td>
		</tr>
	</table>
</body>
</html>
