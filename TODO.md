# TODO

- Execute the projects' greq files


# Deployment

## Add multi auth sessions
```bash
php migrate.php add-sessions
```

## Add document management feature

### Pre-Deployment Checklist
1. Backup the production database
2. Review code changes
3. Test migration on staging environment

### Deployment Steps

1. **Backup Database**
   ```bash
   cp database/groodo-api.sqlite database/groodo-api.sqlite.backup-$(date +%Y%m%d-%H%M%S)
   ```

2. **Upload Code Changes**
   Upload the following new/modified files to the production server:
   - `src/Models/Document.php` (new)
   - `src/Controllers/DocumentController.php` (new)
   - `src/Services/ValidationService.php` (modified)
   - `src/Utils/Migration.php` (modified)
   - `src/routes.php` (modified)
   - `src/dependencies.php` (modified)
   - `migrate.php` (modified)

3. **Run Database Migration**
   ```bash
   php migrate.php add-documents
   ```
   This will:
   - Create the `documents` table
   - Create necessary indexes (user_id, parent_id, user_parent composite)
   - Set up foreign key constraints

4. **Verify Migration**
   ```bash
   sqlite3 database/groodo-api.sqlite ".schema documents"
   sqlite3 database/groodo-api.sqlite "SELECT COUNT(*) FROM documents;"
   ```

5. **Test API Endpoints**
   - Test document creation: `POST /api/documents`
   - Test listing documents: `GET /api/documents`
   - Test getting single document: `GET /api/document/{id}`
   - Test updating document: `PUT /api/document/{id}`
   - Test nesting (create 5 levels deep)
   - Test deletion prevention (try to delete parent with children)
   - Test deleting leaf document: `DELETE /api/document/{id}`

6. **Monitor Logs**
   ```bash
   tail -f logs/groodo-api-$(date +%Y-%m-%d).log
   ```

### Rollback Plan

If issues occur:

1. **Restore Database Backup**
   ```bash
   cp database/groodo-api.sqlite.backup-YYYYMMDD-HHMMSS database/groodo-api.sqlite
   ```

2. **Revert Code Changes**
   - Restore previous version of files from version control
   - Remove new files (Document.php, DocumentController.php)

3. **Restart Web Server** (if applicable)

### API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/documents | List documents (optional: ?parentId=X) |
| POST | /api/documents | Create new document |
| GET | /api/document/{id} | Get single document |
| PUT/PATCH | /api/document/{id} | Update document |
| DELETE | /api/document/{id} | Delete document (fails if has children) |

### Business Rules
- Documents support nesting up to 5 levels deep
- Cannot delete a document that has child documents
- Title is required (max 256 characters)
- Content is optional (unlimited length)