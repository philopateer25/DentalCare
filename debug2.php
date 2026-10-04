<?php
$user = app('db')->table('users')->where('email', 'dr@clinic.com')->first();
$roles = app('db')->table('model_has_roles')->where('model_id', $user->id)->get();
$roleNames = app('db')->table('roles')->whereIn('id', $roles->pluck('role_id'))->pluck('name');
echo "User ID: {$user->id}\n";
echo "Practice ID: {$user->practice_id}\n";
echo "Roles: " . implode(', ', $roleNames->toArray()) . "\n";
