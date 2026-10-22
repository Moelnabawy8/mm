#!/bin/bash

# Configuration Script for AI Chatbot Backend
# This script helps set up the .env file and restart the application

# Text formatting
BOLD='\033[1m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=================================================${NC}"
echo -e "${BOLD}   AI Chatbot Server Configuration Helper   ${NC}"
echo -e "${BLUE}=================================================${NC}"

# Check if running in the correct directory
if [ ! -d "backend" ]; then
    echo -e "${RED}Error: 'backend' directory not found.${NC}"
    echo "Please run this script from the root project directory (where backend/ and frontend/ are located)."
    exit 1
fi

ENV_FILE="backend/.env"
EXAMPLE_ENV="backend/.env.example"

# Create .env if it doesn't exist
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${YELLOW}No .env file found. Creating one from .env.example...${NC}"
    if [ -f "$EXAMPLE_ENV" ]; then
        cp "$EXAMPLE_ENV" "$ENV_FILE"
        echo -e "${GREEN}.env file created.${NC}"
    else
        echo -e "${RED}Error: .env.example not found. Cannot create .env.${NC}"
        # Create a basic .env if example is missing (fallback)
        touch "$ENV_FILE"
        echo "NODE_ENV=production" >> "$ENV_FILE"
        echo "PORT=3001" >> "$ENV_FILE"
    fi
else
    echo -e "${GREEN}Found existing .env file.${NC}"
fi

# Function to read a value from .env
get_env_value() {
    local key=$1
    local value=$(grep "^$key=" "$ENV_FILE" | cut -d'=' -f2-)
    echo "$value"
}

# Function to update or add a value in .env
set_env_value() {
    local key=$1
    local value=$2
    
    # Escape special characters for sed (simplified)
    # Using a different delimiter for sed to avoid issues with slashes in URLs
    
    if grep -q "^$key=" "$ENV_FILE"; then
        # Key exists, update it
        # Use a temporary file to handle sed's inline editing variability across systems
        sed "s|^$key=.*|$key=$value|" "$ENV_FILE" > "${ENV_FILE}.tmp" && mv "${ENV_FILE}.tmp" "$ENV_FILE"
    else
        # Key doesn't exist, append it
        echo "$key=$value" >> "$ENV_FILE"
    fi
}

# Function to prompt user for input with default value
prompt_config() {
    local key=$1
    local label=$2
    local current_value=$(get_env_value "$key")
    local default_display="${current_value:-<not set>}"
    
    echo -e "\n${BOLD}$label${NC}"
    echo -e "Current value: ${YELLOW}$default_display${NC}"
    read -p "Enter new value (press Enter to keep current): " new_value
    
    if [ -n "$new_value" ]; then
        set_env_value "$key" "$new_value"
        echo -e "${GREEN}Updated $key${NC}"
    else
        echo "Keeping current value."
    fi
}

# --- Configuration Steps ---

echo -e "\n${BOLD}--- Application Settings ---${NC}"
prompt_config "PORT" "Server Port (Default: 3001)"
prompt_config "CORS_ORIGIN" "Allowed Frontend URL (CORS Origin)"

echo -e "\n${BOLD}--- OpenAI Configuration ---${NC}"
prompt_config "OPENAI_API_KEY" "OpenAI API Key"

echo -e "\n${BOLD}--- Database Configuration (Optional) ---${NC}"
prompt_config "DB_HOST" "Database Host (localhost)"
prompt_config "DB_PORT" "Database Port (3306)"
prompt_config "DB_NAME" "Database Name (chatbot_db)"
prompt_config "DB_USER" "Database User"
prompt_config "DB_PASSWORD" "Database Password"

echo -e "\n${BOLD}--- Security ---${NC}"
prompt_config "SESSION_SECRET" "Session Secret (Random string for security)"

# --- Finalizing ---

echo -e "\n${BLUE}=================================================${NC}"
echo -e "${GREEN}Configuration updated successfully!${NC}"

read -p "Do you want to restart the chatbot backend now? (y/n): " restart_confirm
if [[ "$restart_confirm" =~ ^[Yy]$ ]]; then
    echo -e "\n${YELLOW}Restarting via PM2...${NC}"
    
    # Check if PM2 is installed
    if command -v pm2 &> /dev/null; then
        # Try to reload if it exists, otherwise start
        if pm2 list | grep -q "chatbot-backend"; then
             pm2 reload chatbot-backend
        else
             pm2 start ecosystem.config.js
        fi
        
        pm2 save
        echo -e "${GREEN}Chatbot backend restarted successfully!${NC}"
    else
        echo -e "${RED}PM2 is not installed or not found in PATH.${NC}"
        echo "Please install PM2 (npm install -g pm2) or restart the server manually."
    fi
else
    echo "Skipping restart. Changes will take effect next time the server starts."
fi

echo -e "${BLUE}=================================================${NC}"
