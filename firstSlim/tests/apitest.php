<?php
use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Uri;
use Slim\Http\RequestBody;
require './vendor/autoload.php';

// empty class definitions for phpunit to mock.
class mockQuery {
  public function fetchAll(){}
  public function fetch(){}
};
class mockDb {
  public function query(){}
  public function exec(){}
}

class StateTest extends TestCase
{
    protected $app;
    protected $db;

    // execute setup code before each test is run
    public function setUp()
    {
      $this->db = $this->createMock('mockDb');
      $this->app = (new feather\firstSlim\App($this->db))->get();
    }

    // test the helloName endpoint (Keep as a reference)
    public function testHelloName() {
      $env = Environment::mock([
          'REQUEST_METHOD' => 'GET',
          'REQUEST_URI'    => '/hello/Joe',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;
      $response = $this->app->run(true);
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame("Hello, Joe", (string)$response->getBody());
    }

    // test for the GET state request
    public function testGetState() {

      // expected result string
      $resultString = '[{"StateNumber":"1","Capital":"Harrisburg","Initials":"PA","Name":"Pennsylvania"},{"StateNumber":"2","Capital":"Dover","Initials":"DE","Name":"Delaware"},{"StateNumber":"3","Capital":"New York","Initials":"NY","Name":"New York"}]';

      // mock the query class & fetchAll functions
      $query = $this->createMock('mockQuery');
      $query->method('fetchAll')
        ->willReturn(json_decode($resultString, true)
      );
       $this->db->method('query')
             ->willReturn($query);

      // mock the request environment.  (part of slim)
      $env = Environment::mock([
          'REQUEST_METHOD' => 'GET',
          'REQUEST_URI'    => '/states',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;

      $response = $this->app->run(true);
      // make sure the request returns a 200 status code
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame($resultString, (string)$response->getBody());
    }

    public function testGetOneState() {

      //The state with the id of 1 should match the result string on GET request
      $resultString = '{"StateNumber":"1","Capital":"Harrisburg","Initials":"PA","Name":"Pennsylvania"}';
      $query = $this->createMock('mockQuery');
      $query->method('fetch')->willReturn(json_decode($resultString, true));
      $this->db->method('query')->willReturn($query);
      $env = Environment::mock([
          'REQUEST_METHOD' => 'GET',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;

      // actually run the request through the app.
      $response = $this->app->run(true);
      // assert expected status code and body
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame($resultString, (string)$response->getBody());
    }
    #Test to see if getting one state fails
    public function testGetOneStateFail() {
      $query = $this->createMock('mockQuery');
      $query->method('fetch')->willReturn(false);
      $this->db->method('query')->willReturn($query);
      $env = Environment::mock([
          'REQUEST_METHOD' => 'GET',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;

      // actually run the request through the app.
      $response = $this->app->run(true);
      // assert expected status code and body
      $this->assertSame(404, $response->getStatusCode());
      $this->assertSame('{"status":404,"message":"not found"}', (string)$response->getBody());
    }

    //Test to see if the state was updated
    public function testUpdateOneState() {
      // Result string of state with id of 1
      $resultString = '{"StateNumber":"1","Capital":"Harrisburg","Initials":"PA","Name":"Pennsylvania"}';

      $query = $this->createMock('mockQuery');
      $query->method('fetch')
        ->willReturn(json_decode($resultString, true)
      );
      $this->db->method('query')
            ->willReturn($query);
       $this->db->method('exec')
             ->willReturn(true);

      $env = Environment::mock([
          'REQUEST_METHOD' => 'PUT',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $requestBody = ["Capital" =>  "Harrisburg", "Initials" => "PA", "Name" => "Pennsylvania"];
      $req =  $req->withParsedBody($requestBody);
      $this->app->getContainer()['request'] = $req;


      $response = $this->app->run(true);
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame($resultString, (string)$response->getBody());
    }

    // test state to see if user input invalid data
    public function testUpdateOneStateFailed() {
      // expected result string
      $resultString = '{"StateNumber":"1","Capital":"Harrisburg","Initials":"PA","Name":"Pennsylvania"}';

      $query = $this->createMock('mockQuery');
      $query->method('fetch')
        ->willReturn(json_decode($resultString, true)
      );
      $this->db->method('query')
            ->willReturn($query);
       $this->db->method('exec')
          ->will($this->throwException(new PDOException()));

      $env = Environment::mock([
          'REQUEST_METHOD' => 'PUT',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $requestBody = ["Capital" =>  "Harrisburg", "Initials" => "PA", "Name" => "Pennsylvania"];
      $req =  $req->withParsedBody($requestBody);
      $this->app->getContainer()['request'] = $req;

      $response = $this->app->run(true);

      $this->assertSame(400, $response->getStatusCode());
      $this->assertSame('{"status":400,"message":"Invalid data provided to update"}', (string)$response->getBody());
    }

    // test state to see if the state we need to update does not exist
    public function testUpdateOneStateNotFound() {

      $resultString = '{"StateNumber":"1","Capital":"Harrisburg","Initials":"PA","Name":"Pennsylvania"}';


      $query = $this->createMock('mockQuery');
      $query->method('fetch')->willReturn(false);
      $this->db->method('query')
            ->willReturn($query);
       $this->db->method('exec')
          ->will($this->throwException(new PDOException()));

      $env = Environment::mock([
          'REQUEST_METHOD' => 'PUT',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $requestBody = ["Capital" =>  "Harrisburg", "Initials" => "PA", "Name" => "Pennsylvania"];
      $req =  $req->withParsedBody($requestBody);
      $this->app->getContainer()['request'] = $req;

      $response = $this->app->run(true);
      $this->assertSame(404, $response->getStatusCode());
      $this->assertSame('{"status":404,"message":"not found"}', (string)$response->getBody());

    }

    //Check to see if state is actually deleted
    public function testDeleteOneState() {
      $query = $this->createMock('mockQuery');
      $this->db->method('exec')->willReturn(true);
      $env = Environment::mock([
          'REQUEST_METHOD' => 'DELETE',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;

      $response = $this->app->run(true);
      $this->assertSame(200, $response->getStatusCode());
    }

    // Check if deletion failed due to non existant state
    public function testDeleteOneStateFailed() {
      $query = $this->createMock('mockQuery');
      $this->db->method('exec')->willReturn(false);
      $env = Environment::mock([
          'REQUEST_METHOD' => 'DELETE',
          'REQUEST_URI'    => '/state/1',
          ]);
      $req = Request::createFromEnvironment($env);
      $this->app->getContainer()['request'] = $req;

      // actually run the request through the app.
      $response = $this->app->run(true);
      // assert expected status code and body
      $this->assertSame(404, $response->getStatusCode());
      $this->assertSame('{"status":404,"message":"not found"}', (string)$response->getBody());
    }
}
