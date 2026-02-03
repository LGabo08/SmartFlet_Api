<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use HasFactory;
    protected $table = 'usuarios';
    protected $primaryKey = 'idUsuario';
    public $timestamps = true; // si tu tabla ya los tiene

    protected $fillable = [
        'email',
        'nombre',
        'apellidos',
        'contrasena',
        'estado',
        'role_id',
    ];

    protected $hidden = [
        'contrasena',
    ];

    // Laravel Auth usará este campo como password
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    // Relación: Usuario -> Rol
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    // =========================
    // JWT
    // =========================
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // 👉 Aquí mandamos el rol dentro del token
    public function getJWTCustomClaims()
    {
        return [
            'role' => optional($this->role)->nombre,
            'role_id' => $this->role_id,
        ];
    }
}
