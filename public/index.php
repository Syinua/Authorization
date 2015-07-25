<?php
require '../vendor/autoload.php';

// Prepare app
$app = new \Slim\Slim(array(
    'templates.path' => '../templates',
));

// Create monolog logger and store logger in container as singleton
// (Singleton resources retrieve the same log resource definition each time)
$app->container->singleton('log', function () {
    $log = new \Monolog\Logger('slim');
    $log->pushHandler(new \Monolog\Handler\StreamHandler('../logs/app.log', \Monolog\Logger::DEBUG));
    return $log;
});

// Prepare view
$app->view(new \Slim\Views\Twig());
$app->view->parserOptions = array(
    'charset' => 'utf-8',
    'cache' => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());

$app->add(new \Slim\Middleware\SessionCookie(array('name' => 'authuser','secret' => 'mycode')));

$authenticate = function ($app) {
    return function () use ($app) {
        if (!isset($_SESSION['user'])) {
            $_SESSION['urlRedirect'] = $app->request()->getPathInfo();
            $app->flash('error', 'Login required');
            $app->redirect('/');
        }
    };
};
$app->hook('slim.before.dispatch', function() use ($app) {
    $user = null;
    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
    }
    $app->view()->setData('user', $user);
});

// Define routes
$app->get('/', function () use ($app) {
    // Sample log message
    $app->log->info("Slim '/' route");

    $flash = $app->view()->getData('flash');
    $error = "";

    if (isset($flash['error'])) {
        $error = $flash['error'];
    }
    $email_value = $email_error = $password_error = '';

    if (isset($flash['email'])) {
        $email_value = $flash['email'];
    }
    if (isset($flash['errors']['email'])) {
        $email_error = $flash['errors']['email'];
    }
    if (isset($flash['errors']['password'])) {
        $password_error = $flash['errors']['password'];
    }
    if (isset($_SESSION['user'])) {
        $app->render('about.twig');
    }else{
        $app->render('login.twig', array('error' => $error,
                                        'email_value' => $email_value,
                                        'email_error' => $email_error,
                                        'password_error' => $password_error
        ));
    }

});
// Registration routes
$app->get("/registration", function () use ($app) {

    $app->log->info("Slim '/' route");

    $flash = $app->view()->getData('flash');
    $error = "";

    if (isset($flash['error'])) {
        $error = $flash['error'];
    }
    $email_value = $email_error = $password_error = '';

    if (isset($flash['email'])) {
        $email_value = $flash['email'];
    }
    if (isset($flash['errors']['email'])) {
        $email_error = $flash['errors']['email'];
    }
    if (isset($flash['errors']['password'])) {
        $password_error = $flash['errors']['password'];
    }
    $app->render('regist.twig', array('error' => $error,
                                    'email_value' => $email_value,
                                    'email_error' => $email_error,
                                    'password_error' => $password_error
    ));
});

$app->get('/about', $authenticate($app), function () use ($app) {
    $app->render('about.twig');
});

$app->get("/logout", function () use ($app) {
    unset($_SESSION['user']);
    $app->view()->setData('user', null);
    $app->redirect('/');
});

$app->post("/authorization", function () use ($app) {
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $errors = array();
    if (!$email === false){
        $result = new UserModel();
        if ($res = $result->userExistsPDO($email)) {
            //list($email, $pw, $salt, $iteration) = explode(':', $res);
            // if($result->getHash($password, $salt, $iteration) === $pw){
            if ($result->getHash($password, $res['salt'], $res['iter']) === $res['password']) {
                $user = $_SESSION['user'] = $email;
                $app->view()->setData('user', $user);
                $app->render('about.twig');
            } else {
                $app->flash('email', $email);
                $errors['password'] = "Password does not match.";
            }
        } else {
            $errors['email'] = "E-mail is not found.";
        }
    } else {
        $errors['email'] = "E-mail is empty.";
    }
    if (count($errors) > 0) {
        $app->flash('errors', $errors);
        $app->redirect('/');
    }
});

$app->post("/registration", function () use ($app) {
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $errors = array();
    $result = new UserModel();
    if (!$email){
        $errors['email'] = "E-mail is empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) === false){
        if ($res = $result->userExists($email)){
            $errors['email'] = "Username already exists.";
        }elseif (!$password){
            $app->flash('email', $email);
            $errors['password'] = "Password is empty.";
        }
    }else{
        $errors['email'] = "This e-mail address is not valid...";
    }
    if (count($errors) > 0) {
        $app->flash('errors', $errors);
        $app->redirect('/registration');
    }else{
        $result->saveHash($email,$password);
        $app->redirect('/');
    }
});
// Run app
$app->run();