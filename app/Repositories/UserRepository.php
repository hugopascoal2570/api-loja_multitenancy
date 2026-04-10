<?php

namespace App\Repositories;

use App\DTO\Users\CreateUserDTO;
use App\DTO\Users\EditUserDTO;
use App\Models\StoreConfiguration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository {

    public function __construct(protected User $user)
    {
        
    }

    public function getPaginate(int $totalPerPage = 15, int $page = 1, string $filter = ''): LengthAwarePaginator
    {
        return $this->user->where(function ($query) use ($filter) {
            if ($filter !== '') {
                $query->where('name', 'LIKE', "%{$filter}%");
            }
        })
        ->with(['permissions'])
        ->paginate($totalPerPage, ['*'], 'page', $page);
    }

    public function createNew(CreateUserDTO $dto): User
    {
        $data = (array) $dto;
        $data['password'] = bcrypt($data['password']);
        return $this->user->create($data);
    }
    

    public function findById(string $id): ?User {
        return $this->user->find($id);

    }

    public function findByEmail(string $email): ?User
    {
        return $this->user->where('email', $email)->first();
    }

    public function update(EditUserDTO $dto): bool
    {
        if (!$user = $this->findById($dto->id)) {
            return false;
        }

        $data = (array) $dto;
        unset($data['password'], $data['id'], $data['is_admin']);

        if ($dto->password !== null) {
            $data['password'] = bcrypt($dto->password);
        }

        return $user->update($data);
    }
    
    public function delete(string $id):bool {
        if (!$user = $this->findById($id)) {
            return false;
        }
        return $user->delete();
    }
    
    public function syncPermissions(string $id, array $permissions): ?bool
    {
        if (!$user = $this->findById($id)) {
            return null;
        }
        $user->permissions()->sync($permissions);
        return true;
    }
    
    public function getPermissionsByUserId(string $user)
    {
        return $this->findById($user)->permissions()->get();
    }

    public function hasPermissions(User $user, string $permissionName): bool
    {
        $config = StoreConfiguration::current();
        $superAdmins = $config->super_admin_emails ?? config('acl.super_admins', []);

        if (in_array($user->email, $superAdmins)) {
            return true;
        }
    
        return $user->permissions()->where('name', $permissionName)->exists();
    }
 
    public function setAdminStatus(string $id, bool $isAdmin): bool
    {
    if (!$user = $this->findById($id)) {
        return false;
    }

    $user->is_admin = $isAdmin;
    return $user->save();
    }

    public function getOrdersByUserId(string $userId)
    {
        $user = $this->findById($userId);

        if (!$user) {
            return collect();
        }

        return $user->orders()
            ->with('items')
            ->latest()
            ->get();
    }


public function searchByNameOrEmail(string $term, int $totalPerPage = 15, int $page = 1): LengthAwarePaginator
{
    $query = User::query();

    if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
        // Busca direta por e-mail exato quando o termo é um e-mail
        $query->where('email', $term);
    } else {
        // LIKE com wildcard — substitui espaços por % para termos múltiplos
        $like = '%' . str_replace(' ', '%', $term) . '%';

        $query->where(function ($q) use ($like) {
            $q->where('name', 'LIKE', $like)
              ->orWhere('email', 'LIKE', $like);
        });
    }

    // Prioriza match exato (se houver), depois ordena por nome
    $query->orderByRaw(
        'CASE WHEN email = ? THEN 0 WHEN name = ? THEN 1 ELSE 2 END',
        [$term, $term]
    )->orderBy('name');

    // Paginação
    return $query->paginate(
        perPage: $totalPerPage,
        columns: ['*'],
        pageName: 'page',
        page: $page
    );
}

}