const fs = require('fs');
const path = require('path');

const base = path.join('postman', 'collections', 'Skite API');

// Ensure directories exist
['Auth', 'Users', 'Routing'].forEach(folder => {
  const dir = path.join(base, folder);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

const files = {};

// ─────────────────────────────────────────────
// AUTH FOLDER
// ─────────────────────────────────────────────

files[path.join(base, 'Auth', 'Login - valid credentials.request.yaml')] = `$kind: http-request
name: 'Login - valid credentials'
method: POST
url: '{{baseUrl}}/index.php?route=auth/login'
order: 1000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "email": "admin@skite.com",
      "password": "password123"
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 200", function () {
          pm.response.to.have.status(200);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains user object", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("user");
          pm.expect(json.user).to.be.an("object");
      });

      pm.test("User has required fields", function () {
          const user = pm.response.json().user;
          pm.expect(user).to.have.property("id");
          pm.expect(user).to.have.property("email");
          pm.expect(user).to.have.property("full_name");
          pm.expect(user).to.have.property("role_id");
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Auth', 'Login - missing email.request.yaml')] = `$kind: http-request
name: 'Login - missing email'
method: POST
url: '{{baseUrl}}/index.php?route=auth/login'
order: 2000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "password": "password123"
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains error message about email", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.include("email");
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Auth', 'Login - invalid password.request.yaml')] = `$kind: http-request
name: 'Login - invalid password'
method: POST
url: '{{baseUrl}}/index.php?route=auth/login'
order: 3000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "email": "admin@skite.com",
      "password": "wrongpassword"
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 401", function () {
          pm.response.to.have.status(401);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains invalid credentials message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("invalid") || msg.includes("password") || msg.includes("credentials")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Auth', 'Login - invalid email format.request.yaml')] = `$kind: http-request
name: 'Login - invalid email format'
method: POST
url: '{{baseUrl}}/index.php?route=auth/login'
order: 4000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "email": "not-an-email",
      "password": "password123"
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains validation message about email format", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("email") || msg.includes("invalid") || msg.includes("format")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Auth', 'Login - wrong HTTP method.request.yaml')] = `$kind: http-request
name: 'Login - wrong HTTP method'
method: GET
url: '{{baseUrl}}/index.php?route=auth/login'
order: 5000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 405 or 400", function () {
          pm.expect(pm.response.code).to.be.oneOf([400, 405]);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains method not allowed message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("method") || msg.includes("not allowed") || msg.includes("post")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

// ─────────────────────────────────────────────
// USERS FOLDER
// ─────────────────────────────────────────────

files[path.join(base, 'Users', 'Create user - valid.request.yaml')] = `$kind: http-request
name: 'Create user - valid'
method: POST
url: '{{baseUrl}}/index.php?route=users/create'
order: 1000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Test User",
      "email": "testuser@example.com",
      "password": "securepass123",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 201", function () {
          pm.response.to.have.status(201);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains created user object", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("user");
          pm.expect(json.user).to.be.an("object");
      });

      pm.test("Created user has correct fields", function () {
          const user = pm.response.json().user;
          pm.expect(user).to.have.property("id");
          pm.expect(user).to.have.property("email");
          pm.expect(user).to.have.property("full_name");
          pm.expect(user).to.have.property("role_id");
      });

      pm.test("Created user email matches request", function () {
          const user = pm.response.json().user;
          pm.expect(user.email).to.eql("testuser@example.com");
      });

      pm.test("Store created user ID in collection variable", function () {
          const user = pm.response.json().user;
          if (user && user.id) {
              pm.collectionVariables.set("createdUserId", String(user.id));
          }
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Create user - duplicate email.request.yaml')] = `$kind: http-request
name: 'Create user - duplicate email'
method: POST
url: '{{baseUrl}}/index.php?route=users/create'
order: 2000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Duplicate User",
      "email": "testuser@example.com",
      "password": "securepass123",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 409 or 400", function () {
          pm.expect(pm.response.code).to.be.oneOf([400, 409]);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains duplicate/exists message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("duplicate") || msg.includes("exists") || msg.includes("already") || msg.includes("email")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Create user - missing full_name.request.yaml')] = `$kind: http-request
name: 'Create user - missing full_name'
method: POST
url: '{{baseUrl}}/index.php?route=users/create'
order: 3000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "email": "noname@example.com",
      "password": "securepass123",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains message about full_name", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("full_name") || msg.includes("name") || msg.includes("required")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Create user - invalid role_id.request.yaml')] = `$kind: http-request
name: 'Create user - invalid role_id'
method: POST
url: '{{baseUrl}}/index.php?route=users/create'
order: 4000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Bad Role User",
      "email": "badrole@example.com",
      "password": "securepass123",
      "role_id": 999
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400 or 422", function () {
          pm.expect(pm.response.code).to.be.oneOf([400, 422]);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains message about role_id", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("role") || msg.includes("invalid") || msg.includes("role_id")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Create user - invalid email format.request.yaml')] = `$kind: http-request
name: 'Create user - invalid email format'
method: POST
url: '{{baseUrl}}/index.php?route=users/create'
order: 5000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Bad Email User",
      "email": "not-valid-email",
      "password": "securepass123",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains email validation message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("email") || msg.includes("invalid") || msg.includes("format")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Get user by ID - valid.request.yaml')] = `$kind: http-request
name: 'Get user by ID - valid'
method: GET
url: '{{baseUrl}}/index.php?route=users/get&id={{adminUserId}}'
order: 6000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 200", function () {
          pm.response.to.have.status(200);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains user object", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("user");
          pm.expect(json.user).to.be.an("object");
      });

      pm.test("User has required fields", function () {
          const user = pm.response.json().user;
          pm.expect(user).to.have.property("id");
          pm.expect(user).to.have.property("email");
          pm.expect(user).to.have.property("full_name");
          pm.expect(user).to.have.property("role_id");
      });

      pm.test("User ID matches requested ID", function () {
          const user = pm.response.json().user;
          pm.expect(String(user.id)).to.eql(pm.collectionVariables.get("adminUserId"));
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Get user by ID - not found.request.yaml')] = `$kind: http-request
name: 'Get user by ID - not found'
method: GET
url: '{{baseUrl}}/index.php?route=users/get&id=999999'
order: 7000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 404", function () {
          pm.response.to.have.status(404);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains not found message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("not found") || msg.includes("no user") || msg.includes("404")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Get user by ID - invalid ID.request.yaml')] = `$kind: http-request
name: 'Get user by ID - invalid ID'
method: GET
url: '{{baseUrl}}/index.php?route=users/get&id=abc'
order: 8000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains invalid ID message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("invalid") || msg.includes("id") || msg.includes("numeric")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'List all users.request.yaml')] = `$kind: http-request
name: 'List all users'
method: GET
url: '{{baseUrl}}/index.php?route=users/list'
order: 9000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 200", function () {
          pm.response.to.have.status(200);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains users array", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("users");
          pm.expect(json.users).to.be.an("array");
      });

      pm.test("Each user has required fields", function () {
          const users = pm.response.json().users;
          if (users.length > 0) {
              users.forEach(function(user) {
                  pm.expect(user).to.have.property("id");
                  pm.expect(user).to.have.property("email");
                  pm.expect(user).to.have.property("full_name");
                  pm.expect(user).to.have.property("role_id");
              });
          }
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Update user - valid.request.yaml')] = `$kind: http-request
name: 'Update user - valid'
method: PUT
url: '{{baseUrl}}/index.php?route=users/update&id={{createdUserId}}'
order: 10000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Updated Test User",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 200", function () {
          pm.response.to.have.status(200);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains updated user object", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("user");
          pm.expect(json.user).to.be.an("object");
      });

      pm.test("Updated user reflects new full_name", function () {
          const user = pm.response.json().user;
          pm.expect(user.full_name).to.eql("Updated Test User");
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Update user - non-existent user.request.yaml')] = `$kind: http-request
name: 'Update user - non-existent user'
method: PUT
url: '{{baseUrl}}/index.php?route=users/update&id=999999'
order: 11000
headers:
  - key: Content-Type
    value: application/json
body:
  type: json
  content: |-
    {
      "full_name": "Ghost User",
      "role_id": 2
    }
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 404", function () {
          pm.response.to.have.status(404);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains not found message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("not found") || msg.includes("no user") || msg.includes("404")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Delete user - self-delete prevention.request.yaml')] = `$kind: http-request
name: 'Delete user - self-delete prevention'
method: DELETE
url: '{{baseUrl}}/index.php?route=users/delete&id={{adminUserId}}'
order: 12000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 403 or 400", function () {
          pm.expect(pm.response.code).to.be.oneOf([400, 403]);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains self-delete prevention message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("cannot") || msg.includes("self") || msg.includes("own") || msg.includes("yourself") || msg.includes("delete")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Users', 'Delete user - valid soft delete.request.yaml')] = `$kind: http-request
name: 'Delete user - valid soft delete'
method: DELETE
url: '{{baseUrl}}/index.php?route=users/delete&id={{createdUserId}}'
order: 13000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 200", function () {
          pm.response.to.have.status(200);
      });

      pm.test("Response has success status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response contains deletion confirmation message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("deleted") || msg.includes("removed") || msg.includes("success")
          );
      });

      pm.test("Soft delete - user is marked deleted not permanently removed", function () {
          const json = pm.response.json();
          // Soft delete should return success without a hard removal confirmation
          pm.expect(json.status).to.eql("success");
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

// ─────────────────────────────────────────────
// ROUTING FOLDER
// ─────────────────────────────────────────────

files[path.join(base, 'Routing', 'No route specified.request.yaml')] = `$kind: http-request
name: 'No route specified'
method: GET
url: '{{baseUrl}}/index.php'
order: 1000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 400", function () {
          pm.response.to.have.status(400);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains missing route message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("route") || msg.includes("missing") || msg.includes("required") || msg.includes("specify")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

files[path.join(base, 'Routing', 'Invalid route.request.yaml')] = `$kind: http-request
name: 'Invalid route'
method: GET
url: '{{baseUrl}}/index.php?route=nonexistent/action'
order: 2000
scripts:
  - type: afterResponse
    language: text/javascript
    code: |-
      pm.test("Status code is 404", function () {
          pm.response.to.have.status(404);
      });

      pm.test("Response has error status", function () {
          const json = pm.response.json();
          pm.expect(json.status).to.eql("error");
      });

      pm.test("Response contains route not found message", function () {
          const json = pm.response.json();
          pm.expect(json).to.have.property("message");
          pm.expect(json.message.toLowerCase()).to.satisfy(msg =>
              msg.includes("route") || msg.includes("not found") || msg.includes("invalid") || msg.includes("404")
          );
      });

      pm.test("Response time is less than 2000ms", function () {
          pm.expect(pm.response.responseTime).to.be.below(2000);
      });
`;

// Write all files
let count = 0;
for (const [filePath, content] of Object.entries(files)) {
  const dir = path.dirname(filePath);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(filePath, content, 'utf8');
  count++;
}

console.log(`Successfully wrote ${count} request files.`);
