<?php
class AdminerLoginServersExt
{
	private static $servers;
	private $servers_list;
	
	/** Set supported servers
	* @param array [$description => ["server" => ..., "driver" => "server|pgsql|sqlite|...", "group" => [...]]]
	*/
	public function __construct (array $servers)
	{
		$this->servers_list = $this->process_servers($servers);
		
		if (isset($_POST['auth']['server'], self::$servers[$_POST['auth']['server']]))
		{
			$_POST['auth']['driver'] = self::$servers[$_POST['auth']['server']]['driver'];
			
			if (file_exists(Adminer\get_temp_dir() . '/adminer.invalid'))
				unlink(Adminer\get_temp_dir() . '/adminer.invalid');
		}
	}
	
	public function credentials (): array
	{
		return [
			self::$servers[Adminer\SERVER]['server'] ?? null,
			$_GET['username'],
			Adminer\get_password()
		];
	}
	
	public function login (string $login, string $password): bool
	{
		if (!isset(self::$servers[Adminer\SERVER]))
			return false;
		return true;
	}
	
	public function loginFormField (string $name, string $heading, string $value = null)
	{
		if ($name === 'driver')
			return '';
		elseif ($name === 'server')
			return $heading . "<select name=\"auth[server]\">" . Adminer\optionlist($this->servers_list, Adminer\SERVER) . "</select>\n";
	}
	
	public static function get_name ()
	{
		return self::$servers[Adminer\SERVER]['name'] ?? Adminer\SERVER;
	}
	
	public static function get_group ()
	{
		return self::$servers[Adminer\SERVER]['group'] ?? Adminer\SERVER;
	}
	
	private function process_servers (array $servers, ?string $group = null): array
	{
		$output = [];
		
		foreach ($servers as $description => $server)
		{
			if (isset($server['group']) && is_array($server['group']))
			{
				$output[$description] = $this->process_servers($server['group'], $description);
			}
			else
			{
				$output[$server['server']] = $description;
				$server['name'] = $description;
				$server['group'] = $group;
				
				self::$servers[$server['server']] = $server;
			}
		}
		
		return $output;
	}
}
