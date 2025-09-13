<?php
// config.php (ejemplo) -- ponelo en la raíz (junto a contact.php) para pruebas locales.
// IMPORTANTE: rota credenciales si son reales y NO subas esto a un repo público.

return [
  'SMTP_HOST' => 'smtp.gmail.com',
  'SMTP_PORT' => 465, // 465 SMTPS, 587 STARTTLS
  'SMTP_USER' => 'federicodiaz1981@gmail.com',
  'SMTP_PASS' => 'huwl zqcy velq ookk', // tu app-password (ejemplo)
  'SMTP_TO'   => 'fediaz3100@gmail.com',

  // Opcional: nombre para mostrar
  'FROM_NAME' => 'Nueva Celina & Asoc',
];