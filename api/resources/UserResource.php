<?php namespace DMA\Friends\API\Resources;

use Input;
use Request;
use Closure;
use Response;
use Exception;
use Validator;
use ValidationException;
use FriendsAPIAuth;

use DMA\Friends\Classes\AuthManager;
use DMA\Friends\Models\Usermeta;
use DMA\Friends\Classes\UserExtend;
use DMA\Friends\Classes\API\BaseResource;
use DMA\Friends\Wordpress\Auth as WordpressAuth;

use RainLab\User\Models\User;
use RainLab\User\Models\Settings as UserSettings;

use October\Rain\Database\ModelException;
use October\Rain\Auth\AuthException;
use Cms\Classes\Theme;

class UserResource extends BaseResource
{
    
    protected $model        = '\RainLab\User\Models\User';
    protected $transformer  = '\DMA\Friends\API\Transformers\UserTransformer';
    
    
    /**
     * The following API actions in the UserResource are public.
     * It means API Authentication will not be enforce.
     * @var array
     */
    public $publicActions = ['login', 'loginByCard', 'verifyMembership', 'store', 'profileOptions'];
        
    /**
     * Hacky variable to include user profile only when 
     * showing a single user
     * @var boolean
     */
    private $include_profile = false;
    
    public function __construct()
    {
        // Add additional routes to Activity resource
        $this->addAdditionalRoute('login',           'login',                    ['POST']);
        $this->addAdditionalRoute('loginByCard',     'login/card',               ['POST']);
        $this->addAdditionalRoute('verifyMembership','verify-membership',        ['POST']);
        $this->addAdditionalRoute('uploadAvatar',    '{user}/upload-avatar',     ['POST', 'PUT']);
        $this->addAdditionalRoute('profileOptions',  'profile-options/{field}',  ['GET']);
        $this->addAdditionalRoute('profileOptions',  'profile-options',          ['GET']);
        $this->addAdditionalRoute('userActivities',  '{user}/activities',        ['GET']);
        $this->addAdditionalRoute('userRewards',     '{user}/rewards',           ['GET']);
        $this->addAdditionalRoute('userBadges',      '{user}/badges',            ['GET']);
        $this->addAdditionalRoute('userBookmarks',   '{user}/bookmarks/{type}',  ['GET']);
        
    }
    
    
    
    public function getTransformer()
    {
        $profile = $this->include_profile;
        $this->include_profile = false;
        return new $this->transformer($profile);
        
    }

    /**
     * 
     * @SWG\Definition(
     *      definition="request.user.credentials",
     *      required={"username", "password", "app_key"},
     *      @SWG\Property(
     *         property="username",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="password",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="app_key",
     *         type="string",
     *         format="password"
     *      )        
     * )
     * 
     * @SWG\Definition(
     *      definition="meta.user.login",
     *      
     *      @SWG\Property(
     *         property="token",
     *         type="string"
     *      )
     * )
     *
     * @SWG\Definition(
     *      definition="user.hints.membership",
     *      required={"first_name", "last_name", "email"},
     *      @SWG\Property(
     *         property="first_name",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="last_name",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="email",
     *         type="string"
     *      )            
     * )
     *
     * @SWG\Definition(
     *      definition="user.verify.membership",
     *      required={"message", "hints"},
     *      @SWG\Property(
     *         property="hints",
     *         type="object",
     *         ref="#/definitions/user.hints.membership"
     *      )
     * )
     *
     * @SWG\Definition(
     *      definition="meta.user.verify.membership",
     *      
     *      @SWG\Property(
     *         property="verification_token",
     *         type="string"
     *      )
     * )
     *
     * @SWG\Definition(
     *      definition="response.user.verify.membership",
     *      required={"data"},
     *      @SWG\Property(
     *          property="data",
     *          type="object",
     *          ref="#/definitions/user.verify.membership"
     *      ),
     *      @SWG\Property(
     *          property="meta",
     *          type="object",
     *          ref="#/definitions/meta.user.verify.membership"
     *      )      
     * )
     *
     *
     *
     * @SWG\Definition(
     *      definition="response.user.login",
     *      required={"data"},
     *      @SWG\Property(
     *          property="data",
     *          type="object",
     *          ref="#/definitions/user.extended"
     *      ),
     *      @SWG\Property(
     *          property="meta",
     *          type="object",
     *          ref="#/definitions/meta.user.login"
     *      )      
     * )
     * 
     * @SWG\Definition(
     *      definition="response.user.verify.membership",
     *      required={"data"},
     *      @SWG\Property(
     *          property="data",
     *          type="object",
     *          ref="#/definitions/user.extended"
     *      ),
     *      @SWG\Property(
     *          property="meta",
     *          type="object",
     *          ref="#/definitions/meta.user.login"
     *      )      
     * ) 
     * 
     * 
     * @SWG\Post(
     *     path="users/login",
     *     description="Authenticate user using username and password",
     *     summary="User authentication",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         description="User credentials payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.user.credentials")
     *     ), 
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/response.user.login")
     *     ),
     *     @SWG\Response(
     *         response=202,
     *         description="User needs to verify membership",
     *         @SWG\Schema(ref="#/definitions/response.user.verify.membership")
     *     ),     
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/UserError404")
     *     )     
     * )
     */
    
    public function login()
    {
        $data     = Request::all();
        $credentials = [
            'login'     => array_get($data, 'login', array_get($data, 'username', array_get($data, 'email'))),
            'password'  => array_get($data, 'password'),
            'app_key'   => array_get($data, 'app_key', null)
        ];
        
        return $this->authenticate($credentials);
    }
    
     
    
    /**
     *
     * @SWG\Definition(
     *      definition="request.user.credentials.card",
     *      required={"barcode", "app_key"},
     *      @SWG\Property(
     *         property="barcode",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="app_key",
     *         type="string",
     *         format="password"
     *      )
     * )
     *
     * @SWG\Post(
     *     path="users/login/card",
     *     description="Authenticate user using username and password",
     *     summary="User authentication",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         description="User credentials payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.user.credentials.card")
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/response.user.login")
     *     ),
     *     @SWG\Response(
     *         response=202,
     *         description="User needs to verify membership response",
     *         @SWG\Schema(ref="#/definitions/response.user.verify.membership")
     *     ),    
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/UserError404")
     *     )
     * )
     */

    
    public function loginByCard()
    {
        $data     = Request::all();
        $rules = [
                'app_key'    => 'required',
                'barcode'    => 'required'
        ];

        $validation = Validator::make($data, $rules);
        if ($validation->fails()){
            return $this->errorDataValidation('Invalidated credential details', $validation->errors());
        }
                
        $credentials = [
                'login'         => array_get($data, 'barcode', null),
                'app_key'       => array_get($data, 'app_key', null),
                'no_password'   => true,
        ];
        
        return $this->authenticate($credentials);

           
    }

    /**
     * Helper method to handler login with password and login via a card
     * @private
     * @param array $credentials
     * @throws \October\Rain\Auth\AuthException
     */
    protected function authenticate($credentials)
    {
        try {
             
            // Update wordpress passwords if necessary
            WordpressAuth::verifyFromEmail(array_get($credentials, 'email', ''), array_get($credentials, 'password'));
           
            $authData = FriendsAPIAuth::attemp($credentials);            
            $token =  array_get($authData, 'token',  null);
    
            if ($user = array_get($authData, 'user',   null)) {
                $this->include_profile = true;
                return Response::api()->withItem($user, $this->getTransformer(), null, ['token' => $token]);
    
            } else if ($membershipData = array_get($authData, 'membership',   null)) {
                // Return a request to confirm membership
                $payload = [
                        'data' => [
                                'message' => 'This user has a membership outside of the Friends platform. Please verify this user in other to create a Friends profile.',
                                'hints' => array_get($membershipData, 'hints', [])
                        ],
                        'meta' => [
                                'verification_token' => $token
                        ]
                ];
    
                return Response::api()->setStatusCode(202)->withArray($payload);
    
            } else {
                return Response::api()->errorNotFound('User not found');
            }
    
        } catch(Exception $e) {
            if ($e instanceof ValidationException) {
                return $this->errorDataValidation('User credentials fail to validated', $e->getErrors());
    
            } if($e instanceof AuthException ) {
                return Response::api()->errorUnauthorized($e->getMessage());
            } else {
    
                // Lets the API resource deal with the exception
                throw $e;
            }
    
        }
    
    }
    
    
    /**
     *
     * @SWG\Definition(
     *      definition="request.user.verify.membership",
     *      required={"app_key", "verification_token"},
     *      
     *      @SWG\Property(
     *         property="app_key",
     *         type="string",
     *         format="password"
     *      ),          
     *      @SWG\Property(
     *         property="verification_token",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="last_name",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="first_name",
     *         type="string"
     *      ),
     *      @SWG\Property(
     *         property="email",
     *         type="string"
     *      )  
     * )
     *
     *  @SWG\Definition(
     *      definition="user.verified.membership",
     *      
     *      @SWG\Property(
     *         property="membership",
     *         type="object"
     *      )
     * )
     * 
     * @SWG\Definition(
     *      definition="meta.verified.membership",
     *      
     *      @SWG\Property(
     *         property="membership_token",
     *         type="string"
     *      )
     * )
     *
     *
     * @SWG\Definition(
     *      definition="response.user.verified.membership",
     *      required={"data"},
     *      @SWG\Property(
     *          property="data",
     *          type="object",
     *          ref="#/definitions/user.verified.membership"
     *      ),
     *      @SWG\Property(
     *          property="meta",
     *          type="object",
     *          ref="#/definitions/meta.verified.membership"
     *      )      
     * )
     *
     * @SWG\Post(
     *     path="users/verify-membership",
     *     description="Verify user membership",
     *     summary="User membership verification",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         description="User ownership membership payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.user.verify.membership")
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/response.user.verified.membership")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/UserError404")
     *     ),
     *     @SWG\Response(
     *         response=401,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/error401")
     *     ) 
     * )
     */
    
    public function verifyMembership()
    {
        //try {
        $verify   = false;
        $data     = Request::all();
        $messages = [
                'email.required_without_all' => 'The :attribute field is required when first_name or last_name is not present.',
        ];
        $rules = [
                'app_key'            => 'required',
                'verification_token' => 'required',
                'first_name'         => 'required_with:last_name|min:2',
                'last_name'          => 'required_with:first_name|min:2',
                'email'              => 'required_without_all:first_name,last_name|email|between:2,64',
        ];
    
        $validation = Validator::make($data, $rules, $messages);
        if ($validation->fails()){
            return $this->errorDataValidation('Invalid payload', $validation->errors());
        }

        // Decode token an extract membership data
        $token   = array_get($data, 'verification_token', null);
        $tokenData = FriendsAPIAuth::decodeToken($token, 'verify');
        $tokenAppKey = array_get($tokenData, 'aud', null);
        $context     = array_get($tokenData, 'context', []);

        // 1. Validated application key in token match and application that is active
        $appKey   = array_get($data, 'app_key', null);
        if ($appKey  === $tokenAppKey ) {
            $app = FriendsAPIAuth::getAPIApplication($appKey);
        }

       // 2. Verify membership calling the given plugin in the token
       if ($pluginId = array_get($context, 'pluginId', null)) {
           $membership = array_get($context, 'membership', []);
           $verify = AuthManager::verifyMembership($pluginId, $membership, $data);
       }
       
       // Clone membershipdata
       $output = (array)$membership;
       // Remove classname if present
       unset($output['classname']);
       
       if ($verify){
            $payload = [
                    'data' => [
                            "membership" => $output
                     ],  
                    'meta' => [
                            'membership_token' => FriendsAPIAuth::createToken($app, 'membership', $context)
                    ]
            ];
            
            $response = Response::api()->withArray($payload);
        } else {
            $response = Response::api()->errorUnauthorized('Membership verification failed');
        }
    
        return $response;
    }
    

    
    /**
     * @SWG\Get(
     *     path="users/{id}",
     *     description="Returns an user by id",
     *     summary="Find user by id",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/user.extended")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    public function show($id)
    {
        // Hacky variable to make the user transformer 
        // to include the user profile
        $this->include_profile = true;
        return parent::show($id);
    }
    
    
    /** 
     * @SWG\Get(
     *     path="users",
     *     summary="Returns all users",
     *     description="Returns all users",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/per_page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/sort"
     *     ), 
     *     
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/user", type="array")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *    )
     * )
     */
    public function index()
    {
        return parent::index();
    }
    
    /**
     * 
     * @SWG\Definition(
     *     definition="request.user",
     *     type="object",
     *     required={"app_key", "email", "password", "password_confirm"},
     *     
     *     @SWG\Property(
     *         property="app_key",
     *         type="string"
     *     ),      
     *     @SWG\Property(
     *         property="first_name",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="last_name",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="email",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="email_optin",
     *         type="boolean"
     *     ),
     *     @SWG\Property(
     *         property="password",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="password_confirmation",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="address",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         description="State id. Get state using users/profile-options/states",
     *         property="state",
     *         type="integer",
     *         format="int32"
     *     ),
     *     @SWG\Property(
     *         property="zip",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="phone",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="birthday_year",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="birthday_month",
     *         type="string"
     *     ),
     *     @SWG\Property(
     *         property="birthday_day",
     *         type="string"
     *     ),
     *    @SWG\Property(
     *         description="Get an update list from endpoint users/profile-options/gender",  
     *         property="gender",
     *         type="string",
     *         enum={"Male", "Female", "Non Binary/Other"}
     *    ),    
     *    @SWG\Property(
     *         description="Get an update list from endpoint users/profile-options/race",
     *         property="race",
     *         type="string",
     *         enum={"White", "Hispanic", "Black or African American", "American Indian or Alaska Native", "Asian", "Native Hawaiian or Other Pacific Islander", "Two or more races", "Other"}
     *    ),
     *    @SWG\Property(
     *         description="Get an update list from endpoint users/profile-options/household_income",  
     *         property="household_income",
     *         type="string",
     *         enum={"Less then $25,000", "$25,000 - $50,000", "$50,000 - $75,000", "$75,000 - $150,000", "$150,000 - $500,000", "$500,000 or more"}
     *    ),
     *    @SWG\Property(
     *         description="Get an update list from endpoint users/profile-options/education", 
     *         property="education",
     *         type="string",
     *         enum={"K-12", "High School/GED", "Some College", "Vocational or Trade School", "Bachelors Degree", "Masters Degree", "PhD"}
     *    ),
     *    @SWG\Property(
     *         description="Membership token returned by users/verify-membership", 
     *         property="membership_token",
     *         type="string"
     *    )
     * )
     * 
     * @SWG\Post(
     *     path="users",
     *     description="Register a new user",
     *     summary="Register a new user",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         description="User payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.user")
     *     ), 
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/user.extended")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     )
     * )
     */
    
    public function store()
    {
        try{
            $data = Request::all();
            
            // Rules 
            $rules = [
                    'first_name'            => 'min:2',
                    'last_name'             => 'min:2',
                    'birthday_year'         => 'required_with:birthday_month,birthday_day|alpha_num|min:4',
                    'birthday_month'        => 'required_with:birthday_year,birthday_day|alpha_num|min:2',
                    'birthday_day'          => 'required_with:birthday_year,birthday_month|alpha_num|min:2',
                    'app_key'               => 'required',
            ];
            
            // Check if the application key is valid
            // If Application key is invalid or inactive 
            // an exception will raise
            $appKey  = array_get($data, 'app_key', NULL);
            FriendsAPIAuth::isApplicationKeyValid($appKey);
            
            
            // Reformat birthday data structure
            $bd_year  = array_get($data, 'birthday_year', null);
            $bd_month = array_get($data, 'birthday_month', null);
            $bd_day   = array_get($data, 'birthday_day', null);
            
            $data['birthday'] = [
                  'year'    => $bd_year,
                  'month'   => $bd_month,
                  'day'     => $bd_day
            ];
            
            // COMPLETE NEW USER DATA STRUCTURE
            // The API allows to register users with only email and password.
            // so it is necessary to complete the data structure with empty strings
            // when the data is not present. In that way the AuthManager don't complain
            // for missing fields.
            $defaultFields = ['first_name', 'last_name', 'phone', 'street_addr', 'city', 'state', 'zip'];
            foreach($defaultFields as $field){
                $data[$field] = array_get($data, $field, '');
            }
  
            // Validate membership token
            $tokenData = null;
            if( $token = array_get($data, 'membership_token', null)) {
                // Decode membership token an verify that are produced
                // by the same application key the user requesting to use
                $rules = [ 'aud' => "/$appKey/" ];
                $tokenData = FriendsAPIAuth::decodeToken($token, 'membership', $rules);
            }
            
            
            // Register new user
            $user = AuthManager::register($data, $rules);

            // Save membership
            if( $tokenData ) {
                $context     = array_get($tokenData, 'context', []);

                // 2. Save  membership calling the given plugin in the token
                if ($pluginId = array_get($context, 'pluginId', null)) {
                    $membership = array_get($context, 'membership', []);
                    $verify = AuthManager::saveMembership($pluginId, $user, $membership);
                }

            }
            
            
            
            // Authenticate user
            $credentials = [
                'login'     => array_get($data, 'username', array_get($data, 'email')),
                'password'  => array_get($data, 'password'),
                'app_key'   => $appKey    
            ];
    
            # Generated authentication token for the newly created user
            $authData = FriendsAPIAuth::attemp($credentials);
            $user  =  array_get($authData, 'user',   Null);
            $token =  array_get($authData, 'token',  Null);
            
            # Return new user and token
            $this->include_profile = true; 
            return Response::api()->withItem($user, $this->getTransformer(), null, ['token' => $token]);
    
             
        } catch(Exception $e) {
            if ($e instanceof ModelException) {
                return $this->errorDataValidation($e->getMessage());
            } else if ($e instanceof ValidationException) {
                return $this->errorDataValidation('User data fails to validated', $e->getErrors());
            } else {
                // Lets the API resource deal with the exception
                throw $e;
            }
    
        }
    
    }
       
    /**
     * @SWG\Put(
     *     path="users/{id}",
     *     description="Update an existing user",
     *     summary="Update an existing user",
     *     tags={ "user"},
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),     
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         description="User payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.user")
     *     ), 
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/user.extended")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/UserError404")
     *     )
     *     
     * )
     */
    
    /**
     * (non-PHPdoc)
     * @see \DMA\Friends\Classes\API\BaseResource::update()
     */
    
    public function update($id)
    {
        try{
            
            if(is_null($user = User::find($id))){
                return Response::api()->errorNotFound('User not found'); 
            }
            
            $data =  Request::all();
            if(Request::isJson() && ($data == '' || !$data)){
                // Is JSON and data is empty, By default PHP
                // blocks PUT/PATCH methods. So as workaround 
                // get the content and decode it manually
                // if required
                $data = Request::getContent();
                if (!is_array($data)){
                    $data = json_decode($data);
                }

            }
            
            if(!is_array($data)){
                return $this->errorDataValidation('Invalid JSON body');
            }

            $rules = [
                    'first_name'            => 'min:2',
                    'last_name'             => 'min:2',
                    //'username'              => 'required|min:6',
                    'email'                 => 'email|between:2,64',
                    'password'              => 'sometimes|required|confirmed|min:6',
                    'password_confirmation' => 'min:6'
            ];
            
            $validation = Validator::make($data, $rules);
            if ($validation->fails()){
                return $this->errorDataValidation('User data fails to validated', $validation->errors());
            }
            
            // Drop password_confirmation if password is not present
            if(is_null(array_get($data, 'password', null))) {
                unset($data['password_confirmation']);
            }
                        
            // Force to update name field if first_name or last_name present
            $first_name = array_get($data, 'first_name', null);
            $last_name  = array_get($data, 'last_name', null);
            if ( $first_name || $last_name ){
                $first_name = (is_null($first_name)) ? $user->metadata->first_name : $first_name;
                $last_name = (is_null($last_name)) ? $user->metadata->last_name : $last_name;
                
                $data['name'] = $first_name . ' ' . $last_name;
            }
            
            // Update user data
            $userAttrs = ['name' , 'password', 'password_confirmation', 'email',
                          'street_addr', 'city', 'state', 'zip', 'phone'];

            $user = $this->updateModelData($user, $data, $userAttrs);
           

            if($user->save()) {
                // If user save ok them we update usermetadata
                $bd_year = array_get($data, 'birthday_year', null);
                $bd_month = array_get($data, 'birthday_month', null);
                $bd_day = array_get($data, 'birthday_day', null);
                
                $birth_date = null;
                
                if ( $bd_year && $bd_month && $bd_day ) {
                    $data['birth_date'] = $bd_year
                    . '-' .  sprintf("%02s", $bd_month)
                    . '-' .  sprintf("%02s", $bd_day)
                    . ' 00:00:00';
                }
                
                // Save user metadata
                $usermeta = $user->metadata;
                if(!is_null($usermeta)){
                    $userMetadataAttrs = ['first_name' , 'last_name', 'birth_date', 'email_optin',
                                          'gender', 'race', 'household_income', 'household_size', 'education'];
                    $usermeta = $this->updateModelData($usermeta, $data, $userMetadataAttrs);
                    $usermeta->save();
                }
               
            }else{
                throw new Exception('Failed to update user data');
            }
            
          
            return $this->show($user->id);
                               
        } catch(Exception $e) {
            if ($e instanceof ModelException) {
                return $this->errorDataValidation($e->getMessage());
            } else {
                // Let the API resource deal with the exception
                throw $e;
            }

        }
    }
    
    
    /**
     * @SWG\Definition(
     *     definition="request.avatar",
     *     type="object",
     *     required={"source"},
     *     @SWG\Property(
     *         description="Source can be one of the URLs returned by  users/profile-options/avatar endpoint or by uploading a Base64 encode string of a JPG, PNG or GIF",
     *         property="source",
     *         type="string"
     *     )
     * )
     * 
     * @SWG\Post(
     *     path="users/{id}/upload-avatar",
     *     summary="Change user avatar",
     *     description="Change user avatar. Avatar must be a valid JPG, GIF or PNG. And not bigger that 400x400 pixels.",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Parameter(
     *         description="Avatar payload",
     *         name="body",
     *         in="body",
     *         required=true,
     *         schema=@SWG\Schema(ref="#/definitions/request.avatar")
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(
     *              @SWG\Property(
     *                  property="success",
     *                  type="boolean"
     *              )
     *         )
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="User not found",
     *         @SWG\Schema(ref="#/definitions/UserError404")
     *     )
     * )
     */
    
    public function uploadAvatar($userId)
    {

        if(is_null($user = User::find($userId))){
            return Response::api()->errorNotFound('User not found');
        }
        
        $data = Request::all();
        $rules = [
                'source'            => 'required',
        ];
        
        $validation = Validator::make($data, $rules);
        if ($validation->fails()){
            return $this->errorDataValidation('Data fails to validated', $validation->errors());
        }        
        
        if($source = array_get($data, 'source', null)){
            
            // Check if is a selected avatar from the theme
            $avatars    = $this->getThemeAvatarOptions();
            $avatars    = array_keys($avatars);
            $keyAvatar  = trim(strtolower(basename(basename($source))));
            
            if(in_array($keyAvatar, $avatars)){
                UserExtend::uploadAvatar($user, $source);
            }else{
                UserExtend::uploadAvatarFromString($user, $source);
            }
            
            return [ 'success' => true];
            
        } 
    }
  
    
    /**
     * @SWG\Definition(
     *     definition="response.profile.options",
     *     type="object",
     *     @SWG\Property(
     *          property="gender",
     *          type="array",
     *          items=@SWG\Schema(type="string")
     *     ),     
     *     @SWG\Property(
     *          property="race",
     *          type="array",
     *          items=@SWG\Schema(type="string")
     *     ),     
     *     @SWG\Property(
     *          description="Currently hardcode to return only USA states",
     *          property="states",
     *          type="array",
     *          items=@SWG\Schema(ref="#/definitions/state")
     *     ),     
     *     @SWG\Property(
     *          property="household_income",
     *          type="array",
     *          items=@SWG\Schema(type="string")
     *     ),     
     *     @SWG\Property(
     *          property="education",
     *          type="array",
     *          items=@SWG\Schema(type="string")
     *     ),     
     *     @SWG\Property(
     *          property="avatars",
     *          type="array",
     *          items=@SWG\Schema(type="string")
     *     ) 
     * )
     * 
     * 
     * @SWG\Get(
     *     path="users/profile-options/{field}",
     *     description="Returns user profile options",
     *     summary="Get profile options",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         description="Return options only for the given field",
     *         in="path",
     *         name="field",
     *         required=false,
     *         type="string",
     *         enum={"gender", "race", "states", "household_income", "education", "avatars"}
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/response.profile.options")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    public function profileOptions($field=null)
    {
        $opts = null;
        
        // Options from Usermeta
        $options = Usermeta::getOptions();
        
        // Avatar options as is used in the UserProfile component
        $options['avatars'] = $this->getThemeAvatarOptions();
        
        if (!is_null($field)) {
            $fieldOpts = array_get($options, strtolower(trim($field)), null);
            if (!is_null($fieldOpts)) {
                $opts = [ $field => $fieldOpts ];
            }else{
                $message = 'Valid fields are: [ ' . implode(', ', array_keys($options)) . ' ]' ;
                return Response::api()->errorInternalError($message);
            }
        }else{
            $opts = $options; // Return all
        }
        
        return $opts;
    }
    
    /**
     * @SWG\Get(
     *     path="users/{id}/activities",
     *     description="Returns an user activities",
     *     summary="Find user completed activities",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/per_page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/page"
     *     ),
     *     
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/activity")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    public function userActivities($userId)
    {
        $transformer  = '\DMA\Friends\API\Transformers\ActivityTransformer';
        $attrRelation = 'activities';
        return $this->genericUserRelationResource($userId, $attrRelation, $transformer);
    }
    
    /**
     * @SWG\Get(
     *     path="users/{id}/rewards",
     *     description="Returns user rewards",
     *     summary="Find user redeem rewards",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/per_page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/page"
     *     ),
     *     
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/reward")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    public function userRewards($userId)
    {
        $transformer  = '\DMA\Friends\API\Transformers\RewardTransformer';
        $attrRelation = 'rewards';
        return $this->genericUserRelationResource($userId, $attrRelation, $transformer);
    }
    
    /**
     * @SWG\Get(
     *     path="users/{id}/badges",
     *     description="Returns user badges",
     *     summary="Find user earned badges",
     *     tags={ "user"},
     *     
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/per_page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/page"
     *     ),
     *     
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/badge")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    public function userBadges($userId)
    {
        $transformer  = '\DMA\Friends\API\Transformers\BadgeTransformer';
        $attrRelation = 'badges';
        return $this->genericUserRelationResource($userId, $attrRelation, $transformer);
    }
    
    
    /**
     * @SWG\Get(
     *     path="users/{id}/bookmarks/{type}",
     *     description="Returns user bookmarks",
     *     summary="Find user bookmarks",
     *     tags={ "user"},
     *
     *     @SWG\Parameter(
     *         ref="#/parameters/authorization"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/per_page"
     *     ),
     *     @SWG\Parameter(
     *         ref="#/parameters/page"
     *     ),
     *
     *     @SWG\Parameter(
     *         description="ID of user to fetch",
     *         format="int64",
     *         in="path",
     *         name="id",
     *         required=true,
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         description="Object type of bookmarks to retrive",
     *         in="path",
     *         name="type",
     *         required=true,
     *         type="string",
     *         enum={"rewards", "badges", "activities"}
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/bookmark")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
    
    public function userBookmarks($userId, $type)
    {
        $transformer  = '\DMA\Friends\API\Transformers\BookmarkTransformer';
        //$attrRelation = 'bookmarks';
        $relation = function($user) use ($type){
            $objectTypes = [
                    'rewards'       => 'DMA\Friends\Models\Reward',
                    'badges'        => 'DMA\Friends\Models\Badge',
                    'activities'    => 'DMA\Friends\Models\Activity'
            ];
            
            $model = array_get($objectTypes, $type);
            if (!$model){
                $options = implode(', ', array_keys($objectTypes));
                throw new \Exception("$type is not a valid option. Options are $options");
            }
            
            $query = $user->bookmarks()
                          ->where('object_type', '=' , $model);
            return $query;
        };
        
        return $this->genericUserRelationResource($userId, $relation, $transformer);        
    }
    
    private function genericUserRelationResource($userId, $relation, $transformer)
    {        
        if(is_null($user = User::find($userId))){
            return Response::api()->errorNotFound('User not found');
        }
    
        $pageSize   = $this->getPageSize();
        $sortBy     = $this->getSortBy();
        
        if(is_string($relation)){
            $query = $user->{$relation}();       
        }else if( is_object($relation) && ($relation instanceof Closure) ){
            $query = $relation($user);
        }
        
        $transformer = new $transformer;
        if(method_exists($transformer, 'setUser')){
            $transformer->setUser($user);
        }
    
        if ($pageSize > 0){
            $paginator = $query->paginate($pageSize);
            return Response::api()->withPaginator($paginator, $transformer);
        }else{
            return Response::api()->withCollection($query->get(), $transformer);
        }
    
    }
    
    
    private function getThemeAvatarOptions()
    {
        $avatars = [];
        $theme = Theme::getActiveTheme();
        $themePath =  $theme->getPath();
        $avatarPath = $themePath . '/assets/images/avatars/*.jpg';
        
        // loop through all the files in the plugin's avatars directory and parse the file names
        foreach ( glob($avatarPath ) as $file ) {
            $path = str_replace(base_path(), '', $file);
        
            $avatars[trim(strtolower(basename($path)))] = $path;
        }
        
        return $avatars; 
    }
}
