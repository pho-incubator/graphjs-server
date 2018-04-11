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
use Valitron\Validator;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\UserOut\Create;
use PhoNetworksAutogenerated\Group;
use Pho\Lib\Graph\ID;


/**
 * Takes care of Groups
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class GroupController extends AbstractController
{
    /**
     * Create a new Group
     * 
     * [title, description]
     * 
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * @param string   $id
     * 
     * @return void
     */
    public function createGroup(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['title', 'description']);
        $v->rule('lengthMax', ['title'], 80);
        if(!$v->validate()) {
            $this->fail($response, "Title (up to 80 chars) and Description are required.");
            return;
        }
        $i = $kernel->gs()->node($id);
        $group = $i->create($data["title"], $data["description"]);
        $this->succeed(
            $response, [
            "id" => (string) $group->id()
            ]
        );
    }

    public function setGroup(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        // Avatar, Birthday, About, Username, Email
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Group ID is required.");
            return;
        }
    
        $i = $kernel->gs()->node($id);
        $sets = [];

        $group = $kernel->gs()->node($data["id"]);
        if(!$group instanceof Group) {
            return $this->fail($response, "Valid Group ID is required.");
        }

        $group_owner = $group->edges()->in(Create::class)->current()->tail()->id()->toString();
        if($group_owner!=$id) {
            return $this->fail($response, "You do not have privileges to edit this group.");
        }

        if(isset($data["title"])) {
            $v->rule('lengthMax', ['title'], 80);
            if(!$v->validate()) {
                $this->fail($response, "Title must be 80 chars or less.");
                return;
            }
            $sets[] = "title";
            $i->setTitle($data["title"]);
        }

        if(isset($data["description"])) {
            $sets[] = "description";
            $i->setDescription($data["description"]);
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

    /**
     * Leave Group
     * 
     * [id]
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function leaveGroup(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Group ID  required.");
            return;
        }
        $i = $kernel->gs()->node($id);
        $group = $kernel->gs()->node($data["id"]);

        if(!($group instanceof Group)) {
            $this->fail($response, "Given ID is not associated with a Group");
            return;
        }

        if(!$group->contains($i->id())) {
            $this->fail($response, "User is not a member of given Group");
            return;
        }

        $i->leave($group);
        $this->succeed($response);
    }

    /**
     * Join Group
     * 
     * [id]
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function joinGroup(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Group ID  required.");
            return;
        }
        $i = $kernel->gs()->node($id);
        $group = $kernel->gs()->node($data["id"]);

        if(!($group instanceof Group)) {
            $this->fail($response, "Given ID is not associated with a Group");
            return;
        }

        $i->join($group);
        $this->succeed($response);
    }


    /**
     * List Memberships
     * 
     * Returns group memberships
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function listMemberships(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "User ID  required.");
            return;
        }
        $them = $kernel->gs()->node($data["id"]);
        $q = $this->listGroups($request, $response, $kernel);
        if(!$q[0]) {
            $this->fail($response, "Problem fetching groups");
        }
        $groups = $q[1];
        $their_groups = [];
        foreach($groups as $group) {
            $group_obj = $kernel->gs()->node($group["id"]);
            if($group_obj->contains($them->id()))
                $their_groups[] = $group;
        }
        $this->succeed(
            $response, [
            "groups" => $their_groups
            ]
        );
    }


    /**
     * List Groups
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function listGroups(Request $request, Response $response, Kernel $kernel)
    {
        $groups = [];
        $everything = $kernel->graph()->members();
        foreach($everything as $thing) {
            if($thing instanceof Group) {
                $groups[] = [
                    "id" => (string) $thing->id(),
                    "title" => $thing->getTitle(),
                    "description" => $thing->getDescription(),
                    "creator" => (string) $thing->getCreator()->id(),
                    "count" => (string) count($thing->members())
                ];
            }
        }
        $this->succeed(
            $response, [
            "groups" => $groups
            ]
        );
    }

    function fetchGroup(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Group ID  required.");
            return;
        }
        $group = $kernel->gs()->node($data["id"]);
        if(!$group instanceof Group) {
            $this->fail($response, sprintf("The object with ID %s is not a Group", $data["id"]));
        }
        $info = [
                "id" => (string) $group->id(),
                "title" => $group->getTitle(),
                "description" => $group->getDescription(),
                "creator" => (string) $group->getCreator()->id(),
                "count" => (string) count($group->members())
        ];
        $info["members"] = array_keys(array_filter(
            $group->members(),
            function (/*mixed*/ $value): bool {
                    return ($value instanceof User);
            }
        ));
        $this->succeed(
            $response, [
            "group" => $info
            ]
        );
    }

    /**
     * List Group Members
     * 
     * [id]
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function listMembers(Request $request, Response $response, Kernel $kernel)
    {
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['id']);
        if(!$v->validate()) {
            $this->fail($response, "Group ID  required.");
            return;
        }
        $group = $kernel->gs()->node($data["id"]);
        if(!$group instanceof Group) {
            $this->fail($response, "Given ID is not associated with a Group");
            return;
        }
        $members = array_filter(
            $group->members(),
            function (/*mixed*/ $value): bool {
                    return ($value instanceof User);
            }
        );
        $this->succeed(
            $response, [
            "members" => array_keys($members)
            ]
        );
    }
}
