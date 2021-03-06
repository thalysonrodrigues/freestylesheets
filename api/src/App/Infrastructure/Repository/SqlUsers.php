<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\User;
use App\Domain\Value\Status;
use App\Domain\Value\PasswordRecovery;
use App\Infrastructure\Repository\Exception\ManyValuesException;
use Doctrine\DBAL\Connection;

final class SqlUsers implements Users
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return User[]
     */
    public function all(): array
    {
        $allUsers = $this->connection->executeQuery(
            'SELECT ' .
            ' u.id, ' .
            ' u.name, ' .
            ' u.email, ' .
            ' u.password, ' .
            ' u.admin, ' .
            ' u.created_at, ' .
            ' u.status ' .
            'FROM USERS u'
        );

        return $allUsers->fetchAll(
            \PDO::FETCH_FUNC,
            [self::class, 'createUser']
        );
    }

    public function add(User $user): int
    {
        $this->connection->executeUpdate(
            'INSERT INTO USERS (name, email, password, admin, created_at, status) '.
            'VALUES (:name, :email, :password, :admin, :created_at, :status) ',
            [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'admin' => $user->isAdmin() ? 1 : 0,
                'created_at' => $user->getCreatedAt(),
                'status' => $user->isActive() ? 1 : 0
            ]
        );

        return (int) $this->connection->lastInsertId();
    }

    public function findById(int $id): ?User
    {
        $retrieveByIdStatement = $this->connection->executeQuery(
            'SELECT ' .
            ' u.id, ' .
            ' u.name, ' .
            ' u.email, ' .
            ' u.password, ' .
            ' u.admin, ' .
            ' u.created_at, ' .
            ' u.status ' .
            'FROM USERS u ' .
            'WHERE id = :id',
            [
                'id' => (string) $id
            ]
        );

        $userData = $retrieveByIdStatement->fetch(\PDO::FETCH_ASSOC);

        if ( ! $userData) {
            return null;
        }

        return $this->createUser(
            $userData['id'],
            $userData['name'],
            $userData['email'],
            $userData['password'],
            (bool) $userData['admin'],
            $userData['created_at'],
            (bool) $userData['status']
        );
    }

    public function findByEmail(string $email): ?User
    {
        $retrieveByEmailStatement = $this->connection->executeQuery(
            'SELECT ' .
            ' u.id, ' .
            ' u.name, ' .
            ' u.email, ' .
            ' u.password, ' .
            ' u.admin, ' .
            ' u.created_at, ' .
            ' u.status ' .
            'FROM USERS u ' .
            'WHERE email = :email',
            [
                'email' => (string) $email
            ]
        );

        $userData = $retrieveByEmailStatement->fetch(\PDO::FETCH_ASSOC);

        if ( ! $userData) {
            return null;
        }

        return $this->createUser(
            $userData['id'],
            $userData['name'],
            $userData['email'],
            $userData['password'],
            (bool) $userData['admin'],
            $userData['created_at'],
            (bool) $userData['status']
        );
    }

    public function edit(User $user): bool
    {
        return (bool) $this->connection->executeUpdate(
            'UPDATE USERS SET name = :name, email = :email WHERE id = :id',
            [
                'name' => $user->getName(),
                'email' => $user->getEmail()
            ]
        );
    }

    public function editPartial(int $id, array $data): bool
    {
        if (count($data) !== 1) {
            throw ManyValuesException::message();
        }

        $query = key($data) . " = :" . key($data);

        $data['id'] = $id;

        return (bool) $this->connection->executeUpdate(
            "UPDATE USERS SET {$query} WHERE id = :id",
            $data
        );
    }

    public function enableOrDisableUser(User $user): bool
    {
        return (bool) $this->connection->executeUpdate(
            'UPDATE USERS SET status = :status WHERE id = :id',
            [
                'id' => (string) $user->getId(),
                'status' => $user->getStatus() ? 1 : 0
            ]
        );
    }

    public function remove(int $id): int
    {
        return $this->connection->executeUpdate(
            'DELETE FROM USERS WHERE id = :id',
            [
                'id' => $id
            ]
        );
    }

    public function count(array $filters = []): int
    {
        $filter = new UsersFilters($filters);

        return (int) $this->connection->executeQuery(
            "SELECT COUNT(*) AS total FROM USERS AS user " .
            $filter->where(false),
            $filter->data() ?: []
        )->fetchColumn();
    }

    public function createUser(
        string $id,
        string $name,
        string $email,
        string $password,
        bool $admin,
        string $createdAt,
        bool $status
    ): User
    {
        return User::fromNativeData(
            (int) $id,
            $name,
            $email,
            $password,
            $admin,
            $createdAt,
            $status
        );
    }

    public function registerRecoveryPassword(PasswordRecovery $recovery): bool
    {
        return (bool) $this->connection->executeUpdate(
            'INSERT INTO PASSWORD_RECOVERY (jti, latest, id_user) VALUES (:jti, :latest, :id_user)',
            [
                'jti' => $recovery->getJti()->getValue(),
                'latest' => $recovery->getLatest(),
                'id_user' => $recovery->getUser()->getId()
            ]
        );
    }

    public function checkMaxRequireChangePassword(int $idUser, int $maxDays): int
    {
        return (int) $this->connection->executeQuery(
            'SELECT COUNT(*) AS total FROM PASSWORD_RECOVERY WHERE id_user = :id_user AND DATE_FORMAT(latest, "%Y-%m-%d") BETWEEN CURRENT_DATE() - INTERVAL :max_days DAY AND CURRENT_DATE()',
            [
                'id_user' => $idUser,
                'max_days' => $maxDays
            ]
        )->fetchColumn();
    }
}
