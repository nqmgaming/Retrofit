<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../include/DbConnect.php';
require __DIR__ . '/../include/DbOperations.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->post('/createuser', function (Request $request, Response $response) {

    $request_data = $request->getParsedBody();
    $response = haveEmptyParameters(array('email', 'password', 'name', 'school'), $response, $request_data);

    $response_data = json_decode($response->getBody(), true);
    if (isset($response_data['error']) && $response_data['error'] === true) {
        return $response;
    }


    $email = $request_data['email'];
    $password = $request_data['password'];
    $school = $request_data['school'];
    $name = $request_data['name'];

    $hash_password = password_hash($password, PASSWORD_DEFAULT);

    $db = new DbOperations;

    $result = $db->createUser($email, $hash_password, $name, $school);

    if ($result == USER_CREATED) {
        $message = array();
        $message['error'] = false;
        $message['message'] = 'User created successfully';

        $response->getBody()->write(json_encode($message));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(201);
    } elseif ($result == USER_FAILURE) {
        $message = array();
        $message['error'] = true;
        $message['message'] = 'Some error occurred';

        $response->getBody()->write(json_encode($message));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(422);
    } elseif ($result == USER_EXISTS) {
        $message = array();
        $message['error'] = true;
        $message['message'] = 'User already exists';

        $response->getBody()->write(json_encode($message));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(400);
    }
});

$app->get('/allusers', function (Request $request, Response $response) {
    $db = new DbOperations;
    $users = $db->getAllUser();
    $response_data = array();

    $response_data['error'] = false;
    $response_data['user'] = $users;
    $response->getBody()->write(json_encode($response_data));

    return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(200);
});

$app->put('/userlogin', function (Request $request, Response $response) {

    $request_data = $request->getParsedBody();
    $response = haveEmptyParameters(array('email', 'password'), $response, $request_data);

    $response_data = json_decode($response->getBody(), true);
    if (isset($response_data['error']) && $response_data['error'] === true) {
        return $response;
    }


    $email = $request_data['email'];
    $password = $request_data['password'];

    $db = new DbOperations;

    $result = $db->userLogin($email, $password);
    if ($result == USER_AUTHENTICATED) {

        $user = $db->getUserByEmail($email);

        $response_data = array();
        $response_data['error'] = false;
        $response_data['message'] = 'Login successfully';
        $response_data['user'] = $user;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);

    } elseif ($result == USER_NOT_FOUND) {

        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'User not exits';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404);

    } elseif ($result == USER_PASSWORD_DO_NOT_MATCH) {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'Invalid credential';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404);
    }
});

$app->put('/updateuser/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $request_data = $request->getParsedBody();
    $response = haveEmptyParameters(array('email', 'name', 'school'), $response, $request_data);
    $response_data = json_decode($response->getBody(), true);

    // If there was an error, return the response immediately
    if (isset($response_data['error']) && $response_data['error'] === true) {
        return $response;
    }


    $email = $request_data['email'];
    $school = $request_data['school'];
    $name = $request_data['name'];

    $db = new DbOperations;

    if ($db->updateUser($email, $name, $school, $id)) {
        $response_data = array();
        $response_data['error'] = false;
        $response_data['message'] = 'User updated successfully';
        $user = $db->getUserByEmail($email);
        $response_data['user'] = $user;

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } else {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'Please try again later';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404);
    }
});

$app->delete('/deleteuser/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = new DbOperations;

    if ($db->deleteUser($id)) {
        $response_data = array();
        $response_data['error'] = false;
        $response_data['message'] = 'User has been deleted';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } else {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'Please try again later';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404);
    }
});

function haveEmptyParameters($required_params, $response, $request_data)
{
    $error = false;
    $error_params = '';

    foreach ($required_params as $param) {
        if (!isset($request_data[$param]) || strlen($request_data[$param]) <= 0) {
            $error = true;
            $error_params .= $param . ', ';
        }
    }

    if ($error) {
        $error_detail = array();
        $error_detail['error'] = true;
        $error_detail['message'] = 'Required parameters ' . substr($error_params, 0, -2) . ' are missing or empty';
        $response->getBody()->write(json_encode($error_detail));
        return $response->withHeader('Content-type', 'application/json')->withStatus(400);
    }

    return $response;
}

//update password
$app->put('/updatepassword', function (Request $request, Response $response) {

    $request_data = $request->getParsedBody();
    $response = haveEmptyParameters(array('currentpassword', 'newpassword', 'email'), $response, $request_data);

    $response_data = json_decode($response->getBody(), true);
    if (isset($response_data['error']) && $response_data['error'] === true) {
        return $response;
    }

    $currentpassword = $request_data['currentpassword'];
    $newpassword = $request_data['newpassword'];
    $email = $request_data['email'];

    $db = new DbOperations;

    $result = $db->updatePassword($currentpassword, $newpassword, $email);

    if ($result == PASSWORD_CHANGED) {
        $response_data = array();
        $response_data['error'] = false;
        $response_data['message'] = 'Password changed successfully';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(200);
    } elseif ($result == PASSWORD_NOT_CHANGED) {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'Some error occurred';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(422);
    } elseif ($result == PASSWORD_DO_NOT_MATCH) {
        $response_data = array();
        $response_data['error'] = true;
        $response_data['message'] = 'You have given wrong password';

        $response->getBody()->write(json_encode($response_data));
        return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(422);
    }

});
$app->run();