<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use \CodeIgniter\Events\Events;
use \CodeIgniter\Config\Factories;

class Users extends BaseController
{
    public function index()
    {
        //
    }
    // modify however you want. I use Ajax...
    public function add() {

        checkAjax();

        if (!$this->user->hasPermission('users.create')) {
            $response['success']        = false;
            $response['messages']       = lang("App.invalid_permission");
            return $this->response->setJSON($response);
        }

        $response = array();

        $fields['username']         = $this->request->getPost('username');
        $fields['password']         = $this->request->getPost('password');
        $fields['email']            = $this->request->getPost('email');
        $fields['group']            = $this->request->getPost('group');

        $this->validation->setRules([
            'username'              => ['label' => 'Username', 'rules'          => 'required|max_length[30]|min_length[3]|regex_match[/\A[a-zA-Z0-9\.]+\z/]|is_unique[users.username,id,{id}]'],
            'password'              => ['label' => 'Password', 'rules'          => 'required|min_length[8]'],
            'email'                 => ['label' => 'Email Address', 'rules'     => 'required|valid_email|is_unique[auth_identities.secret,id,{id}]'],
            'group'                 => ['label' => 'Group', 'rules'             => 'required'],
        ]);

        if ($this->validation->run($fields) == FALSE) {

            $response['success']    = false;
            $response['messages']   = $this->validation->getErrors(); //Show Error in Input Form
        } else {

            $users                          = auth()->getProvider();
            $user                           = new User([
                'username'                  => $fields['username'],
                'email'                     => $fields['email'],
                'password'                  => $fields['password'],
                'status'                    => 'OFF',
                'status_message'            => 'New User',
            ]);
            // save the user with the above information
            $users->save($user);
            // get the new ID as we still have work to do
            $user                           = $users->findById($users->getInsertID());
            // set the flag to make user change password on first login
            $user->forcePasswordReset();
            // make sure this is the only group(s) for the user
            $user->syncGroups($fields['group']);

            // Additional work done here..
            $actionClass                    = setting('Auth.actions')['register'] ?? null;
            $action                         = Factories::actions($actionClass)->createIdentity($user);
            $code                           = $action; // do not need this yet though it is set
            $tmpPass                        = $fields['password'];
            // trigger our new Event and send the two variables 
            $confirm                        = Events::trigger('newRegistration', $user, $tmpPass);

            // if eveything went well, notifuy the Admin the user has been added and email sent
            if ($confirm) {
                $response['success']        = true;
                $response['messages']       = lang("App.insert-success");
            } else {
                $response['success']        = false;
                $response['messages']       = lang("App.insert-error");
            }
        }
        return $this->response->setJSON($response);
    }
}
