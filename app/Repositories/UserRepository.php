<?php namespace App\Repositories;

use App\Models\User, App\Models\Role;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Session\SessionManager;
use Illuminate\Filesystem\FileSystem;

class UserRepository extends BaseRepository{

	/**
	 * The Role instance.
	 *
	 * @var App\Models\Role
	 */	
	protected $role;

	/**
	 * The Guard instance.
	 *
	 * @var Illuminate\Contracts\Auth\Guard
	 */	
	protected $auth;

	/**
	 * The FileSystem instance.
	 *
	 * @var Illuminate\Filesystem\FileSystem
	 */	
	protected $file;

	/**
	 * The SessionManager instance.
	 *
	 * @var Illuminate\Session\SessionManager
	 */	
	protected $session;

	/**
	 * Create a new UserRepository instance.
	 *
   	 * @param  App\Models\User $user
	 * @param  App\Models\Role $role
	 * @param  Illuminate\Contracts\Auth\Guard $auth
	 * @param  Illuminate\Filesystem\FileSystem $file
	 * @param  Illuminate\Session\SessionManager $session
	 * @return void
	 */
	public function __construct(
		User $user, 
		Role $role, 
		Guard $auth, 
		FileSystem $file,
		SessionManager $session)
	{
		$this->model = $user;
		$this->role = $role;
		$this->auth = $auth;
		$this->file = $file;
		$this->session = $session;
	}

	/**
	 * Save the User.
	 *
	 * @param  App\Models\User $user
	 * @param  Array  $inputs
	 * @return void
	 */
  	private function save($user, $inputs)
	{		
		if(isset($inputs['vu'])) 
		{
			$user->vu = $inputs['vu'] == 'true';		
		} else {	
			$user->username = $inputs['username'];
			$user->email = $inputs['email'];	
			if(isset($inputs['role'])) {
				$user->role_id = $inputs['role'];	
			} else {
				$role_user = $this->role->where('slug', 'user')->first();
				$user->role_id = $role_user->id;
			}
		}

		$user->save();
	}

	/**
	 * Get users collection.
	 *
	 * @param  int  $n
	 * @param  string  $role
	 * @return Illuminate\Support\Collection
	 */
	public function index($n, $role)
	{
		if($role != 'total')
		{
			return $this->model
			->with('role')
			->whereHas('role', function($q) use($role) {
				$q->whereSlug($role);
			})		
			->orderBy('vu', 'asc')
			->orderBy('created_at', 'desc')
			->paginate($n);			
		}

		return $this->model
		->with('role')		
		->orderBy('vu', 'asc')
		->orderBy('created_at', 'desc')
		->paginate($n);
	}

	/**
	 * Count the users.
	 *
	 * @param  string  $role
	 * @return int
	 */
	public function count($role = null)
	{
		if($role)
		{
			return $this->model
			->whereHas('role', function($q) use($role) {
				$q->whereSlug($role);
			})->count();			
		}

		return $this->model->count();
	}

	/**
	 * Count the users.
	 *
	 * @param  string  $role
	 * @return int
	 */
	public function counts()
	{
		$counts = [
			'admin' => $this->count('admin'),
			'redac' => $this->count('redac'),
			'user' => $this->count('user')
		];

		$counts['total'] = array_sum($counts);

		return $counts;
	}

	/**
	 * Get a user collection.
	 *
	 * @return Illuminate\Support\Collection
	 */
	public function create()
	{
		$select = $this->role->all()->lists('titre', 'id');
		$statut = $this->getStatut();

		return compact('select', 'statut');
	}

	/**
	 * Create a user.
	 *
	 * @param  array  $inputs
	 * @param  int    $user_id
	 * @return App\Models\User 
	 */
	public function store($inputs)
	{
		$user = new $this->model;

		$user->password = bcrypt($inputs['password']);

		$this->save($user, $inputs);

		return $user;
	}

	/**
	 * Get user collection.
	 *
	 * @param  string  $slug
	 * @return Illuminate\Support\Collection
	 */
	public function show($id)
	{
		$user = $this->model->with('role')->findOrFail($id);

		$statut = $this->getStatut();

		return compact('user' ,'statut');
	}

	/**
	 * Get user collection.
	 *
	 * @param  int  $id
	 * @return Illuminate\Support\Collection
	 */
	public function edit($id)
	{
		$user = $this->model->findOrFail($id);

		$select = $this->role->all()->lists('titre', 'id');

		return compact('user', 'select');
	}

	/**
	 * Update a user.
	 *
	 * @param  array  $inputs
	 * @param  int    $id
	 * @return void
	 */
	public function update($inputs, $id)
	{
		$user = $this->model->findOrFail($id);

		$this->save($user, $inputs);
	}

	/**
	 * Get statut of authenticated user.
	 *
	 * @return string
	 */
	public function getStatut()
	{
		return $this->session->get('statut');
	}

	/**
	 * Create and return directory name for redactor.
	 *
	 * @return string
	 */
	public function getName()
	{
		$name = strtolower(strtr(utf8_decode($this->auth->user()->username), 
			utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 
			'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
		));

		$directory = base_path() . config('medias.url-files') . $name;

		if (!$this->file->isDirectory($directory))
		{
			$this->file->makeDirectory($directory); 
		}  

		return $name;  
	}

	/**
	 * Valide user.
	 *
     * @param  bool  $valid
     * @param  int   $id
	 * @return void
	 */
	public function valide($valid, $id)
	{
		$user = $this->model->findOrFail($id);

		$user->valid = $valid == 'true';

		$user->save();
	}

}