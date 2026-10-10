package authz

import (
	"context"
	"database/sql"
)

type User struct {
	ID, Email          string
	Roles, Permissions []string
}

func Load(ctx context.Context, db *sql.DB, id, modelType string) (User, error) {
	user := User{Roles: []string{}, Permissions: []string{}}
	if err := db.QueryRowContext(ctx, `SELECT id,email FROM users WHERE id=?`, id).Scan(&user.ID, &user.Email); err != nil {
		return User{}, err
	}
	rows, err := db.QueryContext(ctx, `SELECT DISTINCT r.name FROM roles r JOIN model_has_roles mr ON mr.role_id=r.id WHERE mr.model_id=? AND mr.model_type=? AND r.guard_name='api'`, id, modelType)
	if err != nil {
		return User{}, err
	}
	defer rows.Close()
	for rows.Next() {
		var role string
		if err := rows.Scan(&role); err != nil {
			return User{}, err
		}
		user.Roles = append(user.Roles, role)
	}
	if err := rows.Err(); err != nil {
		return User{}, err
	}
	if err := rows.Close(); err != nil {
		return User{}, err
	}
	rows, err = db.QueryContext(ctx, `SELECT DISTINCT p.name FROM permissions p JOIN role_has_permissions rp ON rp.permission_id=p.id JOIN model_has_roles mr ON mr.role_id=rp.role_id WHERE mr.model_id=? AND mr.model_type=? AND p.guard_name='api' UNION SELECT DISTINCT p.name FROM permissions p JOIN model_has_permissions mp ON mp.permission_id=p.id WHERE mp.model_id=? AND mp.model_type=? AND p.guard_name='web'`, id, modelType, id, modelType)
	if err != nil {
		return User{}, err
	}
	defer rows.Close()
	for rows.Next() {
		var permission string
		if err := rows.Scan(&permission); err != nil {
			return User{}, err
		}
		user.Permissions = append(user.Permissions, permission)
	}
	return user, rows.Err()
}
func (u User) Has(permission string) bool {
	for _, p := range u.Permissions {
		if p == permission {
			return true
		}
	}
	return false
}
