CREATE TABLE operators (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    totp_secret TEXT NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    role VARCHAR(20) NOT NULL DEFAULT 'operator',
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE agents (
    id CHAR(36) PRIMARY KEY,
    name TEXT NOT NULL,
    greeting_message TEXT NOT NULL DEFAULT '',
    system_prompt TEXT NOT NULL DEFAULT '',
    provider VARCHAR(20) NOT NULL DEFAULT 'openai',
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o',
    temperature FLOAT NOT NULL DEFAULT 0.7,
    max_tokens INT NOT NULL DEFAULT 2048,
    config JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    id CHAR(36) PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE conversations (
    id CHAR(36) PRIMARY KEY,
    agent_id CHAR(36) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active_bot',
    assigned_operator_id CHAR(36) NULL,
    metadata JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (assigned_operator_id) REFERENCES operators(id),
    INDEX idx_session_id (session_id),
    INDEX idx_status (status)
);

CREATE TABLE messages (
    id CHAR(36) PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    tool_calls JSON NULL,
    sender_type VARCHAR(10) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id)
);

CREATE TABLE memory (
    id CHAR(36) PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    memory_key VARCHAR(255) NOT NULL,
    memory_value TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_session_key (session_id, memory_key)
);
