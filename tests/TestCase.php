<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected $proc_stream;
    protected $pipes = [];
    protected $client;
    protected $founder_id = '';
    protected $faker;
    protected $jar; // for cookies
    protected $founder_username;
    protected $founder_password;

    const HOST = "http://localhost:1338";

    public function setUp()
    {
        $dotenv = new \Dotenv\Dotenv(dirname(__DIR__));
        $dotenv->load();
        $this->founder_username = getenv("FOUNDER_NICKNAME");
        $this->founder_password = getenv("FOUNDER_PASSWORD");
        $this->faker = Faker\Factory::create();

        if (!file_exists('tests/logs')) {
            mkdir('tests/logs');
        }

        $descriptorspec = array(
            0 => array("file", 'tests/logs/input.txt', 'w'), // stdin is a pipe that the child will read from
            1 => array("file", 'tests/logs/output.txt', 'w'), // stdout is a pipe that the child will write to
            2 => array("file", 'tests/logs/error-output.txt', 'a'), // stderr is a file to write to
        );
        
        $this->proc_stream = proc_open('php ../run.php', $descriptorspec, $this->pipes);
        //`php ../run.php`; //Can be done bu simple run in anoher proccess, but this proccess can not be kill from there.
        
        sleep(0.1);
        $this->client = new \GuzzleHttp\Client();
        $this->jar = new \GuzzleHttp\Cookie\CookieJar;
        $body = $this->login();
        $this->founder_id = $body["id"];
        $this->logout();
    }

    public function tearDown()
    {
        if (isset($this->pipes[0]) && is_rsource($this->pipes[0])) fclose($this->pipes[0]);
        if (isset($this->pipes[1]) && is_rsource($this->pipes[1])) fclose($this->pipes[1]);
        if (is_resource($this->proc_stream)) proc_close($this->proc_stream);
    }

    public function signup(): array
    {
        $email = $this->faker->email;
        $username = substr(str_replace(".","", $this->faker->username),0,10);
        $password = rand(10000, 999999);
        $url = sprintf('/signup?username=%s&password=%s&email=%s', urlencode($username), urlencode($password), urlencode($email));
        $res = $this->get($url, false, true);
        return [
            $email, $username, $password, $res
        ];
    }

    protected function login($username="", $password = "")
    {
        $url = sprintf('/login?username=%s&password=%s', 
            (empty($username) ? urlencode($this->founder_username) : urlencode($username)), 
            (empty($password) ? urlencode($this->founder_password) : urlencode($password))
        );

        $res = $this->get($url, false, true);
        return $res;
    }

    protected function logout(): void
    {
        $this->get("/logout", false, true);
    }

    protected function getAdminHash(): string
    {
        return md5($this->founder_password);
    }

    protected function get(string $path, bool $headers = false, bool $cookies = false)
    {
        if(!$cookies)
            $res = $this->client->request('GET', self::HOST . $path);
        else 
            $res = $this->client->request('GET', self::HOST . $path, [
                'cookies' => $this->jar
            ]);

        if ($headers) {
            return $res;
        }

        $body = json_decode($res->getBody(), true);
        return $body;
    }

    protected function post(string $path, array $postData, bool $headers = false, bool $cookies = false)
    {
        if(!$cookies)
            $res = $this->client->request('POST', self::HOST . $path, [ \GuzzleHttp\RequestOptions::JSON => $postData]);
        else 
            $res = $this->client->request('POST', self::HOST . $path, [ 
               'form_params' => $postData,
                'cookies' => $this->jar
            ]);

        if ($headers) {
            return $res;
        }

        $body = json_decode($res->getBody(), true);
        return $body;
    }

    protected function delete(string $path, array $postData = [], bool $headers = false, bool $cookies = false)
    {
        if(!$cookies)
            $res = $this->client->request('DELETE', self::HOST . $path, ['form_params' => $postData]);
        else 
            $res = $this->client->request('GET', self::HOST . $path, [
                'cookies' => $this->jar
            ]);

        if ($headers) {
            return $res;
        }

        $body = json_decode($res->getBody(), true);
        return $body;
    }

}
