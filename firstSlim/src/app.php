<?php
namespace feather\firstSlim;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
require './vendor/autoload.php';

class App
{

   private $app;
   public function __construct($db) {

     $config['db']['host']   = 'localhost';
     $config['db']['user']   = 'root';
     $config['db']['pass']   = 'root';
     $config['db']['dbname'] = 'states';

     $app = new \Slim\App(['settings' => $config]);

     $container = $app->getContainer();
     $container['db'] = $db;

     $container['logger'] = function($c) {
         $logger = new \Monolog\Logger('my_logger');
         $file_handler = new \Monolog\Handler\StreamHandler('./logs/app.log');
         $logger->pushHandler($file_handler);
         return $logger;
     };

     /* Keep this function to use as a reference of a working endpoint
     $app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
         $name = $args['name'];
         $this->logger->addInfo('get request to /hello/'.$name);
         $response->getBody()->write("Hello, $name");

         return $response;
     });
     */

     //states will display a list of every state in the database
     $app->get('/states', function (Request $request, Response $response) {
         $this->logger->addInfo("GET /StateNumber");
         $state = $this->db->query('SELECT * from states')->fetchAll();
         $jsonResponse = $response->withJson($state);
         return $jsonResponse;
     });
     //Retrieve the state by the id sent in the URL
     $app->get('/state/{id}', function (Request $request, Response $response, array $args) {
         $id = $args['id'];
         $this->logger->addInfo("GET /statenumber/".$id);
         $state = $this->db->query('SELECT * from states where StateNumber='.$id)->fetch();

         //If state is not null, send information in JSON, else show 404 error
         if($state){
           $response =  $response->withJson($state);
         } else {
           $errorData = array('status' => 404, 'message' => 'not found');
           $response = $response->withJson($errorData, 404);
         }
         return $response;

     });
     //Change the ID of a state through the URL using state ID
     $app->put('/state/{id}', function (Request $request, Response $response, array $args) {
         $id = $args['id'];
         $this->logger->addInfo("PUT /statenumber/".$id);

         // check that the state actually exists in database
         $state = $this->db->query('SELECT * from states where StateNummber='.$id)->fetch();
         if(!$state){
           $errorData = array('status' => 404, 'message' => 'not found');
           $response = $response->withJson($errorData, 404);
           return $response;
         }

         // build query string
         $updateString = "UPDATE StateNumber SET ";
         $fields = $request->getParsedBody();
         $keysArray = array_keys($fields);
         $last_key = end($keysArray);
         foreach($fields as $field => $value) {
           $updateString = $updateString . "$field = '$value'";
           if ($field != $last_key) {
             // conditionally add a comma to avoid sql syntax problems
             $updateString = $updateString . ", ";
           }
         }
         $updateString = $updateString . " WHERE statenumber = $id;";

         // execute query
         try {
           $this->db->exec($updateString);
         } catch (\PDOException $e) {
           $errorData = array('status' => 400, 'message' => 'Invalid data provided to update');
           return $response->withJson($errorData, 400);
         }
         // return updated record
         $state = $this->db->query('SELECT * from states where StateNumber='.$id)->fetch();
         $jsonResponse = $response->withJson($state);

         return $jsonResponse;
     });
     //Delete state using DELETE and the state id number
     $app->delete('/state/{id}', function (Request $request, Response $response, array $args) {
       $id = $args['id'];
       $this->logger->addInfo("DELETE /StateNumber/".$id);
       $deleteSuccessful = $this->db->exec('DELETE FROM states where StateNumber='.$id);
       if($deleteSuccessful){
         $response = $response->withStatus(200);
       } else {
         $errorData = array('status' => 404, 'message' => 'not found');
         $response = $response->withJson($errorData, 404);
       }
       return $response;
     });

     $this->app = $app;
   }

   /**
    * Get an instance of the application.
    *
    * @return \Slim\App
    */
   public function get()
   {
       return $this->app;
   }
 }
