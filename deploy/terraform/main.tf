terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# VPC Configuration
resource "aws_vpc" "veribits" {
  cidr_block           = "10.2.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name        = "veribits-vpc"
    Environment = var.environment
  }
}

# Internet Gateway
resource "aws_internet_gateway" "veribits" {
  vpc_id = aws_vpc.veribits.id

  tags = {
    Name = "veribits-igw"
  }
}

# Public Subnets (for ALB and NAT)
resource "aws_subnet" "public_1" {
  vpc_id                  = aws_vpc.veribits.id
  cidr_block              = "10.2.1.0/24"
  availability_zone       = "${var.aws_region}a"
  map_public_ip_on_launch = true

  tags = {
    Name = "veribits-public-1"
  }
}

resource "aws_subnet" "public_2" {
  vpc_id                  = aws_vpc.veribits.id
  cidr_block              = "10.2.2.0/24"
  availability_zone       = "${var.aws_region}b"
  map_public_ip_on_launch = true

  tags = {
    Name = "veribits-public-2"
  }
}

# Private Subnets (for EC2, RDS, ElastiCache)
resource "aws_subnet" "private_1" {
  vpc_id            = aws_vpc.veribits.id
  cidr_block        = "10.2.10.0/24"
  availability_zone = "${var.aws_region}a"

  tags = {
    Name = "veribits-private-1"
  }
}

resource "aws_subnet" "private_2" {
  vpc_id            = aws_vpc.veribits.id
  cidr_block        = "10.2.11.0/24"
  availability_zone = "${var.aws_region}b"

  tags = {
    Name = "veribits-private-2"
  }
}

# Route Tables
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.veribits.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.veribits.id
  }

  tags = {
    Name = "veribits-public-rt"
  }
}

resource "aws_route_table_association" "public_1" {
  subnet_id      = aws_subnet.public_1.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table_association" "public_2" {
  subnet_id      = aws_subnet.public_2.id
  route_table_id = aws_route_table.public.id
}

# Security Groups
resource "aws_security_group" "alb" {
  name        = "veribits-alb-sg"
  description = "Security group for Application Load Balancer"
  vpc_id      = aws_vpc.veribits.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "veribits-alb-sg"
  }
}

resource "aws_security_group" "ec2" {
  name        = "veribits-ec2-sg"
  description = "Security group for EC2 instances"
  vpc_id      = aws_vpc.veribits.id

  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = var.ssh_allowed_cidrs
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "veribits-ec2-sg"
  }
}

resource "aws_security_group" "rds" {
  name        = "veribits-rds-sg"
  description = "Security group for RDS PostgreSQL"
  vpc_id      = aws_vpc.veribits.id

  ingress {
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.ec2.id]
  }

  tags = {
    Name = "veribits-rds-sg"
  }
}

resource "aws_security_group" "redis" {
  name        = "veribits-redis-sg"
  description = "Security group for ElastiCache Redis"
  vpc_id      = aws_vpc.veribits.id

  ingress {
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.ec2.id]
  }

  tags = {
    Name = "veribits-redis-sg"
  }
}

# RDS PostgreSQL
resource "aws_db_subnet_group" "veribits" {
  name       = "veribits-db-subnet"
  subnet_ids = [aws_subnet.private_1.id, aws_subnet.private_2.id]

  tags = {
    Name = "veribits-db-subnet"
  }
}

resource "aws_db_instance" "veribits" {
  identifier             = "veribits-postgres"
  engine                 = "postgres"
  engine_version         = "16.1"
  instance_class         = var.db_instance_class
  allocated_storage      = 100
  storage_type           = "gp3"
  storage_encrypted      = true
  db_name                = "veribits"
  username               = var.db_username
  password               = var.db_password
  db_subnet_group_name   = aws_db_subnet_group.veribits.name
  vpc_security_group_ids = [aws_security_group.rds.id]
  backup_retention_period = 7
  backup_window          = "03:00-04:00"
  maintenance_window     = "sun:04:00-sun:05:00"
  skip_final_snapshot    = var.environment == "production" ? false : true
  final_snapshot_identifier = var.environment == "production" ? "veribits-final-snapshot-${formatdate("YYYY-MM-DD-hhmm", timestamp())}" : null
  multi_az               = var.environment == "production" ? true : false
  publicly_accessible    = false

  tags = {
    Name        = "veribits-postgres"
    Environment = var.environment
  }
}

# ElastiCache Redis
resource "aws_elasticache_subnet_group" "veribits" {
  name       = "veribits-redis-subnet"
  subnet_ids = [aws_subnet.private_1.id, aws_subnet.private_2.id]
}

resource "aws_elasticache_replication_group" "veribits" {
  replication_group_id       = "veribits-redis"
  description                = "Redis cluster for VeriBits"
  engine                     = "redis"
  engine_version             = "7.1"
  node_type                  = var.redis_node_type
  num_cache_clusters         = var.environment == "production" ? 2 : 1
  parameter_group_name       = "default.redis7"
  port                       = 6379
  subnet_group_name          = aws_elasticache_subnet_group.veribits.name
  security_group_ids         = [aws_security_group.redis.id]
  automatic_failover_enabled = var.environment == "production" ? true : false
  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token
  snapshot_retention_limit   = 5
  snapshot_window            = "03:00-04:00"

  tags = {
    Name        = "veribits-redis"
    Environment = var.environment
  }
}

# Application Load Balancer
resource "aws_lb" "veribits" {
  name               = "veribits-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = [aws_subnet.public_1.id, aws_subnet.public_2.id]

  enable_deletion_protection = var.environment == "production" ? true : false

  tags = {
    Name        = "veribits-alb"
    Environment = var.environment
  }
}

resource "aws_lb_target_group" "veribits" {
  name     = "veribits-tg"
  port     = 80
  protocol = "HTTP"
  vpc_id   = aws_vpc.veribits.id

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/api/v1/health"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 2
  }

  tags = {
    Name = "veribits-tg"
  }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.veribits.arn
  port              = "80"
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.veribits.arn
  }
}

# Launch Template for EC2
resource "aws_launch_template" "veribits" {
  name_prefix   = "veribits-"
  image_id      = var.ami_id
  instance_type = var.instance_type
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.ec2.id]

  iam_instance_profile {
    name = aws_iam_instance_profile.ec2.name
  }

  user_data = base64encode(templatefile("${path.module}/user_data.sh", {
    db_host         = aws_db_instance.veribits.address
    db_port         = aws_db_instance.veribits.port
    db_name         = aws_db_instance.veribits.db_name
    db_username     = var.db_username
    db_password     = var.db_password
    redis_host      = aws_elasticache_replication_group.veribits.primary_endpoint_address
    redis_port      = 6379
    redis_password  = var.redis_auth_token
    jwt_secret      = var.jwt_secret
    stripe_secret   = var.stripe_secret_key
    stripe_public   = var.stripe_publishable_key
    stripe_webhook  = var.stripe_webhook_secret
    environment     = var.environment
  }))

  tag_specifications {
    resource_type = "instance"
    tags = {
      Name        = "veribits-app"
      Environment = var.environment
    }
  }
}

# Auto Scaling Group
resource "aws_autoscaling_group" "veribits" {
  name                = "veribits-asg"
  vpc_zone_identifier = [aws_subnet.private_1.id, aws_subnet.private_2.id]
  target_group_arns   = [aws_lb_target_group.veribits.arn]
  health_check_type   = "ELB"
  health_check_grace_period = 300
  min_size            = var.asg_min_size
  max_size            = var.asg_max_size
  desired_capacity    = var.asg_desired_capacity

  launch_template {
    id      = aws_launch_template.veribits.id
    version = "$Latest"
  }

  tag {
    key                 = "Name"
    value               = "veribits-app"
    propagate_at_launch = true
  }

  tag {
    key                 = "Environment"
    value               = var.environment
    propagate_at_launch = true
  }
}

# IAM Role for EC2
resource "aws_iam_role" "ec2" {
  name = "veribits-ec2-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "ec2_ssm" {
  role       = aws_iam_role.ec2.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_instance_profile" "ec2" {
  name = "veribits-ec2-profile"
  role = aws_iam_role.ec2.name
}

# S3 Bucket for file uploads (if needed)
resource "aws_s3_bucket" "veribits_uploads" {
  bucket = "veribits-uploads-${var.environment}"

  tags = {
    Name        = "veribits-uploads"
    Environment = var.environment
  }
}

resource "aws_s3_bucket_public_access_block" "veribits_uploads" {
  bucket = aws_s3_bucket.veribits_uploads.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Outputs
output "alb_dns_name" {
  description = "DNS name of the Application Load Balancer"
  value       = aws_lb.veribits.dns_name
}

output "rds_endpoint" {
  description = "RDS PostgreSQL endpoint"
  value       = aws_db_instance.veribits.address
  sensitive   = true
}

output "redis_endpoint" {
  description = "ElastiCache Redis endpoint"
  value       = aws_elasticache_replication_group.veribits.primary_endpoint_address
  sensitive   = true
}

output "s3_bucket" {
  description = "S3 bucket for uploads"
  value       = aws_s3_bucket.veribits_uploads.id
}
