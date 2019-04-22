<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\UserOut\Follow;
use Pho\Lib\Graph\ID;


/**
 * Takes care of Profile
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class ProfileController extends AbstractController
{
    /**
     * Get Profile
     * 
     * [id]
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function getProfile(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            $this->fail($response, "Valid user ID required.");
            return;
        }
        if(!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["id"])) {
            $this->fail($response, "Invalid user ID");
            return;
        }
        try {
            $user = $kernel->gs()->node($data["id"]);
        }
        catch(\Exception $e) {
            $this->fail($response, "Invalid ID");
            return;
        }
        if(!$user instanceof User) {
            $this->fail($response, "Invalid user ID");
            return;
        }
        $this->succeed(
            $response, [
            "profile" => 
                array_merge(
                    array_change_key_case(
                        array_filter(
                            $user->attributes()->toArray(), 
                            function (string $key): bool {
                                return strtolower($key) != "password";
                            },
                            ARRAY_FILTER_USE_KEY
                        ), CASE_LOWER
                    ),
                    [
                        "follower_count" => \count(\iterator_to_array($user->edges()->in(Follow::class))),
                        "following_count" => \count(\iterator_to_array($user->edges()->out(Follow::class))),
                        "membership_count" => isset($user->toArray()["memberships"]) ? \count($user->toArray()["memberships"]) : 0,
                    ] 
                )
            ]
        );
    }

    /**
     * Set Profile
     * 
     * [avatar, birthday, about, username]
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * @param string   $id
     * 
     * @return void
     */
    public function setProfile(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        // Avatar, Birthday, About, Username, Email
        $data = $request->getQueryParams();
        

        $i = $kernel->gs()->node($id);
        $sets = [];

        if(isset($data["username"])) {
             if(!preg_match("/^[a-zA-Z0-9_]{1,12}$/", $data["username"])) {
                $this->fail($response, "Invalid username");
                return;
            }
            $sets[] = "username";
            $i->setUsername($data["username"]);
        }

        if(isset($data["password"])) {
            if(!preg_match("/[0-9A-Za-z!@#$%_]{5,15}/", $data["password"])) {
               $this->fail($response, "Invalid password");
               return;
           }
           $sets[] = "password";
           $i->setPassword($data["password"]);
       }

        if(isset($data["birthday"])) {
            /*
            $validation = $this->validator->validate($data, [
                'birthday' => 'date|before:13 years ago',
            ]);
            if($validation->fails()) {
                $this->fail($response, "Birthday invalid.");
                return;
            }
            */
            try {
                $dt = \DateTime::createFromFormat('m/d/Y', $data["birthday"]);
                
            }
            catch(\Exception $e) {
                return $this->fail($response, "Birthday invalid. - 2");
            }
            $i->setBirthday($dt->getTimestamp());
            $sets[] = "birthday";
        }

        if(isset($data["avatar"])) {
            $validation = $this->validator->validate($data, [
                'avatar' => 'url',
            ]);
            if($validation->fails()) {
                $this->fail($response, "Avatar URL invalid.");
                return;
            }
            $sets[] = "avatar";
            $i->setAvatar($data["avatar"]);
        }
     
     if(isset($data["email"])) {
            $validation = $this->validator->validate($data, [
                'email' => 'email',
            ]);
            if($validation->fails()) {
                $this->fail($response, "Email is invalid.");
                return;
            }
            $sets[] = "email";
            $i->setEmail($data["email"]);
        }

        if(isset($data["about"])) {
            $sets[] = "about";
            $i->setAbout($data["about"]);
        }


        if($kernel->graph() instanceof \PhoNetworksAutogenerated\Site) { // Graph.js only
            for($m=1;$m<4;$m++) {
                $custom_field = "custom_field".$m;
                if(isset($data[$custom_field])) {
                    $sets[] = $custom_field;
                    $i->attributes()->$custom_field = $data[$custom_field];
                }
            }
        }

        if(count($sets)==0) {
            $this->fail($response, "No field to set");
            return;
        }
        $this->succeed(
            $response, [
            "message" => sprintf(
                "Following fields set successfully: %s", 
                implode(", ", $sets)
            )
            ]
        );
    }
}
