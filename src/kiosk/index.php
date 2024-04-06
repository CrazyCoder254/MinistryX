<?php

require '../Include/Config.php';

require_once __DIR__ . '/../vendor/autoload.php';

$rootPath = str_replace('/kiosk/index.php', '', $_SERVER['SCRIPT_NAME']);
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskDevice;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->compile();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($rootPath . '/kiosk');

$app->add(VersionMiddleware::class);
$app->add(AuthMiddleware::class);
$app->addBodyParsingMiddleware();

// Set up
require __DIR__ . '/../Include/slim/error-handler.php';

// routes
require __DIR__ . '/routes/kiosk.php';

$windowOpen = new \DateTimeImmutable(SystemConfig::getValue('sKioskVisibilityTimestamp')) > new \DateTimeImmutable();

if (isset($_COOKIE['kioskCookie'])) {
    $g = hash('sha256', $_COOKIE['kioskCookie']);
    $Kiosk = KioskDeviceQuery::create()
          ->findOneByGUIDHash($g);
    if ($Kiosk === null) {
        setcookie('kioskCookie', '', ['expires' => time() - 3600]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
    }
}

if (!isset($_COOKIE['kioskCookie'])) {
    if ($windowOpen) {
        $guid = uniqid();
        setcookie('kioskCookie', $guid, ['expires' => 2_147_483_647]);
        $Kiosk = new KioskDevice();
        $Kiosk->setGUIDHash(hash('sha256', $guid));
        $Kiosk->setAccepted(false);
        $Kiosk->save();
    } else {
        header('HTTP/1.1 401 Unauthorized');
        exit;
    }
}
$app->kiosk = $Kiosk;

// Run app
$app->run();
