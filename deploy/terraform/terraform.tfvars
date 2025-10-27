# AWS Configuration
aws_region = "us-east-1"
environment = "production"

# Database Configuration
db_username = "veribits_admin"
db_password = "VB_SecureDB_2025_10_26_P@ssw0rd_9x7K2mL4"
db_instance_class = "db.t3.micro"

# Redis Configuration
redis_auth_token = "VB_Redis_2025_Auth_T0ken_8K3nM9pQ2xL5"
redis_node_type = "cache.t3.micro"

# EC2 Configuration
instance_type = "t3.micro"
key_pair_name = "nitetext-key"
ami_id = "ami-0c7217cdde317cfec"

# Auto Scaling
asg_min_size = 1
asg_max_size = 2
asg_desired_capacity = 1

# JWT Configuration
jwt_secret = "VB_JWT_Secret_2025_SuperSecure_Key_64BytesLong_M9K2L3N4P5Q6R7S8T9U0"

# Stripe Configuration
stripe_secret_key = "sk_test_placeholder_replace_with_real_key"
stripe_publishable_key = "pk_test_placeholder_replace_with_real_key"
stripe_webhook_secret = "whsec_placeholder_replace_with_real_secret"

# SSH Access (optional - restrict to your IP)
ssh_allowed_cidrs = ["0.0.0.0/0"]
