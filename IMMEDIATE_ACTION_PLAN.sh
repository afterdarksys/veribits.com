#!/bin/bash
# IMMEDIATE ACTION PLAN - Get Auth Working for Interview

echo "==========================================="
echo "IMMEDIATE FIX: Bypass Validator Temporarily"
echo "==========================================="
echo ""
echo "This will get login working in < 5 minutes"
echo ""

# Option 1: Quick bypass in AuthController
cat > /tmp/authcontroller_bypass.patch <<'EOF'
--- a/app/src/Controllers/AuthController.php
+++ b/app/src/Controllers/AuthController.php
@@ -95,9 +95,21 @@
         }

         $body = Request::getJsonBody();
+
+        // TEMPORARY BYPASS - Remove after debugging
+        if (empty($body)) {
+            // Try reading directly
+            $body = json_decode(file_get_contents('php://input'), true) ?: [];
+        }
+        if (empty($body)) {
+            // Try $_POST fallback
+            $body = $_POST;
+        }

-        $validator = new Validator($body);
+        // SKIP VALIDATOR - Manual checks only
+        if (empty($body['email']) || empty($body['password'])) {
+            Response::error('Email and password are required', 400);
+            return;
+        }

-        $validator->required('email')->email('email')
-                  ->required('password')->string('password');
-
-        if (!$validator->isValid()) {
-            Response::validationError($validator->getErrors());
-            return;
-        }

-        $email = $validator->sanitize('email');
+        $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
         $password = $body['password'];
EOF

echo "Apply this patch:"
echo "  cd /Users/ryan/development/veribits.com"
echo "  patch -p1 < /tmp/authcontroller_bypass.patch"
echo ""
echo "Then deploy:"
echo "  bash scripts/deploy-auth-fix.sh"
echo ""
echo "============================"
echo "OR: Manual Edit (Faster)"
echo "============================"
echo ""
echo "Edit app/src/Controllers/AuthController.php:"
echo ""
echo "REPLACE lines 96-102 with:"
cat <<'EOF'

        $body = Request::getJsonBody();

        // TEMPORARY: Direct read fallback
        if (empty($body)) {
            $raw = @file_get_contents('php://input');
            $body = json_decode($raw, true) ?: $_POST;
        }

        // TEMPORARY: Skip Validator, use manual checks
        if (empty($body['email']) || empty($body['password'])) {
            Response::error('Email and password required', 400);
            return;
        }

        $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
        $password = $body['password'];

        // Continue with normal login logic...
EOF

echo ""
echo "Then build and deploy:"
echo "  docker build -t veribits-api:bypass -f docker/Dockerfile ."
echo "  docker tag veribits-api:bypass 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest"
echo "  docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest"
echo ""
echo "Update task definition:"
echo "  # Get new digest"
echo "  NEW_DIGEST=\$(aws ecr describe-images --repository-name veribits-api --image-ids imageTag=latest --query 'imageDetails[0].imageDigest' --output text)"
echo "  # Update and deploy"
echo "  python3 scripts/update-task-def.py --digest \$NEW_DIGEST"
echo ""
echo "====================================="
echo "FASTEST: Direct Container Edit (30 seconds)"
echo "====================================="
echo ""
echo "1. Get running task:"
echo "   TASK=\$(aws ecs list-tasks --cluster veribits-cluster --service-name veribits-api --query 'taskArns[0]' --output text | cut -d'/' -f3)"
echo ""
echo "2. Connect to container:"
echo "   aws ecs execute-command --cluster veribits-cluster --task \$TASK --container veribits-api --interactive --command '/bin/bash'"
echo ""
echo "3. Inside container, edit file:"
echo "   vi /var/www/src/Controllers/AuthController.php"
echo ""
echo "4. Find line ~96 with 'Request::getJsonBody()'"
echo ""
echo "5. Add after that line:"
cat <<'EOF'
   // EMERGENCY: Direct fallback
   if (empty($body)) {
       $body = json_decode(@file_get_contents('php://input'), true) ?: $_POST;
   }
EOF
echo ""
echo "6. Comment out Validator lines (~98-106)"
echo ""
echo "7. Change line ~108 to:"
echo "   \$email = filter_var(\$body['email'] ?? '', FILTER_SANITIZE_EMAIL);"
echo ""
echo "8. Restart Apache:"
echo "   apache2ctl restart"
echo ""
echo "9. Test immediately:"
echo "   curl -X POST https://www.veribits.com/api/v1/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"straticus1@gmail.com\",\"password\":\"TestPassword123!\"}'"
echo ""
echo "====================================="
echo "Test Accounts"
echo "====================================="
echo "straticus1@gmail.com / TestPassword123!"
echo "enterprise@veribits.com / EnterpriseDemo2025!"
