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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GraphJS\Session;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\Thread;
use PhoNetworksAutogenerated\UserOut\Start;
use PhoNetworksAutogenerated\UserOut\Reply;
use Pho\Lib\Graph\ID;
use Pho\Lib\Graph\TailNode;



/**
 * Takes care of Forum
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class ForumController extends AbstractController
{

    public function deleteForumPost(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = Session::depend($request))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Entity ID unavailable.");
            
        }
        $entity = $this->kernel->gs()->entity($data["id"]);
        $deleted = [];
        if($entity instanceof Thread) {
            if($entity->edges()->in(Start::class)->current()->tail()->id()->toString()==$id) {
                $deleted[] = (string) $entity->id();
                // replies automatically deleted
                $entity->destroy();
                return $this->succeed($response, [
                    "deleted" => $deleted
                ]);
            }
            return $this->fail($response, "You are not the owner of this thread.");
        }
        elseif($entity instanceof Reply) {
            if($entity->tail()->id()->toString()==$id) {
                $deleted[] = (string) $entity->id();
                $entity->destroy();
                return $this->succeed($response, [
                    "deleted" => $deleted
                ]);
            }
            return $this->fail($response, "You are not the owner of this reply.");
        }
        
        return $this->fail($response, "The ID does not belong to a thread or reply.");
    }

    /**
     * Start Forum Thread 
     * 
     * [title, message]
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @param Kernel   $this->kernel
     * @param string   $id
     * 
     * @return void
     */
    public function startThread(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = Session::depend($request))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'title' => 'required|max:80',
            'message' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Title (up to 80 chars) and Message are required.");
            
        }
        $i = $this->kernel->gs()->node($id);
        $thread = $i->start($data["title"], $data["message"]);
        return $this->succeed(
            $response, [
            "id" => (string) $thread->id()
            ]
        );
    }

    /**
     * Reply Forum Thread
     * 
     * [id, message]
     *
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * 
     * @return void
     */
    public function reply(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = Session::depend($request))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
            'message' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Thread ID and Message are required.");
            
        }
        $i = $this->kernel->gs()->node($id);
        $thread = $this->kernel->gs()->node($data["id"]);
        if(!$thread instanceof Thread) {
            return $this->fail($response, "Given  ID is not associated with a forum thread.");
            
        }
        $reply = $i->reply($thread, $data["message"]);
        return $this->succeed(
            $response, [
            "id" => (string) $reply->id()
            ]
        );
    }

 
    public function editForumPost(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = Session::depend($request))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
            'content' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Message ID and Content are required.");
            
        }
        $i = $this->kernel->gs()->node($id);
        $entity = $this->kernel->gs()->entity($data["id"]);
        if(!$entity instanceof Thread && !$entity instanceof Reply) {
            return $this->fail($response, "Incompatible entity type.");
            
        }
        try {
        $i->edit($entity)->setContent($data["content"]);
        }
     catch(\Exception $e) {
        return $this->fail($response, $e->getMessage());
            
     }
     return $this->succeed($response);
    }

    protected static function _extractProfile(User $user): array
    {
        return array_change_key_case(
            array_filter(
                $user->attributes()->toArray(), 
                function (string $key): bool {
                    return strtolower($key) != "password";
                },
                ARRAY_FILTER_USE_KEY
            ), CASE_LOWER
        );
    }

    /**
     * Get Threads
     * 
     * with number of replies
     *
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     
     * @param Kernel   $this->kernel
     * 
     * @return void
     */
    public function getThreads(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getQueryParams();
        $threads = [];
        $everything = $this->kernel->graph()->members();
        
        foreach($everything as $thing) {
            if($thing instanceof Thread) {
                if($thing->id()->toString()=="5b20c740445f7d1f1ad723fc16b693d5") continue;
                try {
                    $author = $thing->edges()->in(Start::class)->current()->tail();
                }
                catch(\Error $e) {
                    continue;
                }
                $contributors_x = [];
                $contributors_x[$author->id()->toString()] = static::_extractProfile($author);
                $contributors = array_map(
                    function(User $u) : array  {
                        return [  $u->id()->toString() => static::_extractProfile($u) ];
                    }, array_map( 
                        function(Reply $r): User {
                            return $r->tail()->node();
                        }, 
                        $thing->getReplies()
                    )
                );
                $contributors[] = static::_extractProfile($author);
                foreach($contributors as $contributor) {
                    foreach($contributor as $k=>$v) {
                        if(!isset($contributors_x[$k]))
                            $contributors_x[$k] = $v;
                    }
                }
                // unset($contributors);
                try {
                    if(is_null($thing->edges()->in(Start::class)->current())) 
                        continue;
                }
                catch(\Error $e) {
                    // either Exception or Error
                    //error_log("there was an exception at ".$thing->id()->toString());
                    continue;
                }
                catch(\Exception $e) {
                    continue;
                }
                $threads[] = [
                    "id" => (string) $thing->id(),
                    "title" => $thing->getTitle(),
                    "author" => (string) $author->id(),
                    "timestamp" => (string) $thing->getCreateTime(),
                    "contributors" => $contributors_x
                ];
            }
        }


        $threads_count = count($threads);
        $threads = array_values($this->paginate($threads, $params, 20));

        return $this->succeed(
            $response, [
                "threads" => $threads,
                "total"   => $threads_count
            ]
        );
    }

    /**
     * Get Thread
     * 
     * [id]
     *
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     * @param Kernel   $this->kernel
     * 
     * @return void
     */
    public function getThread(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Thread ID required.");
            
        }
        $thread = $this->kernel->gs()->node($data["id"]);
        if(!$thread instanceof Thread) {
            return $this->fail($response, "Not a Thread");
        }
        $replies = $thread->getReplies();
        return $this->succeed(
            $response, [
            "title" => $thread->getTitle(),
            "messages" => array_merge(
                [[
                    "id" => (string) $thread->id(),
                    "author" => (string) $thread->edges()->in()->current()->tail()->id(),
                    "content" => $thread->getContent(),
                    "timestamp" => (string) $thread->getCreateTime()
                ]],
                array_map(
                    function ($obj): array {
                        return [
                            "id" => (string) $obj->id(),
                            "author" => (string) $obj->tail()->id(),
                            "content" => $obj->getContent(),
                            "timestamp" => (string) $obj->getReplyTime()
                        ];
                    },
                    $replies
                )
            )
            ]
        );
    }
}
