# Plano de Implementação - Fase 2 (Chat SDK PHP)

Este plano descreve a implementação da Fase 2 do chat-sdk-php em PHP 8 puro, sem frameworks, sem Composer e sem Docker.

## 1. Componentes Core (core/)

### 1.1. core/Crypto.php
- **Objetivo**: Encriptação e decriptação AES-256-CBC.
- **Funções**:
  - `encrypt(string $data, string $key): string`: Gera um IV de 16 bytes com `openssl_random_pseudo_bytes`, encripta os dados usando a chave (derivada com hash sha256) e retorna a junção binária do `IV + ciphertext` codificada em Base64.
  - `decrypt(string $data, string $key): ?string`: Decodifica o Base64, extrai o IV de 16 bytes e o ciphertext, e decripta o valor.
- **Regras**: Sem comentários no código.

### 1.2. core/JWT.php
- **Objetivo**: Encoder e decoder de tokens JWT em PHP puro usando o algoritmo HMAC-SHA256.
- **Funções**:
  - `encode(array $payload, string $secret, int $expiry = 86400): string`: Codifica o cabeçalho fixo `{"alg":"HS256","typ":"JWT"}` e o payload em Base64Url. Assina a concatenação usando `hash_hmac` com sha256.
  - `decode(string $token, string $secret): ?array`: Valida a estrutura, expiração (`exp`) e assinatura. Retorna o payload decodificado ou `null`.
- **Regras**: Sem comentários no código.

### 1.3. core/TOTP.php
- **Objetivo**: Gerador e validador de TOTP com base no HMAC-SHA1.
- **Funções**:
  - `generateSecret(int $length = 16): string`: Gera uma chave secreta aleatória codificada em Base32.
  - `getOTP(string $secret, ?int $time = null): string`: Calcula o código TOTP de 6 dígitos para o intervalo de tempo de 30 segundos usando HMAC-SHA1.
  - `verify(string $secret, string $code, int $discrepancy = 1, ?int $time = null): bool`: Valida o TOTP aceitando uma janela de discrepância configurável.
  - `getQrCodeUrl(string $label, string $secret, string $issuer = 'ChatSDK'): string`: Retorna o link da API do Google Charts para renderizar o QR Code do TOTP.
- **Regras**: Sem comentários no código.

### 1.4. core/Auth.php
- **Objetivo**: Middleware de autenticação que valida o JWT recebido no cabeçalho `Authorization: Bearer <token>`.
- **Funções**:
  - `handle(Request $request): array`: Extrai o token, valida com `JWT::decode` e recupera os dados do operador no banco de dados. Caso falhe, responde com HTTP 401 Unauthorized e encerra a execução.
- **Regras**: Sem comentários no código.

### 1.5. core/LLMRouter.php
- **Objetivo**: Efetuar chamadas HTTP via cURL puro para os provedores OpenAI, Anthropic e Google Gemini, usando as chaves de API desencriptadas.
- **Funções**:
  - `call(string $provider, string $model, string $systemPrompt, array $messages, float $temperature = 0.7, int $maxTokens = 2048): string`: Encaminha as mensagens para o endpoint correto de cada LLM e retorna a resposta de texto de forma síncrona.
- **Regras**: Sem comentários no código.

---

## 2. Serviços (services/)

### 2.1. services/AuthService.php
- **Responsabilidades**:
  - `register(array $data)`: Regista operador no banco (hash de password com BCRYPT, gera UUID).
  - `login(array $data)`: Valida email e password. Retorna o operador, se exige 2FA (`requires2Fa`), e um token JWT inicial.
  - `setup2Fa(string $operatorId)`: Cria e salva o segredo TOTP para o operador, gerando a URL do QR Code.
  - `verify2Fa(string $operatorId, string $token)`: Valida o código TOTP enviado pelo operador. Se correto, ativa permanentemente o 2FA na conta (`totp_enabled = 1`) e retorna um novo JWT final com `is2FaVerified = true`.

### 2.2. services/ChatService.php
- **Responsabilidades**:
  - `getOrCreateConversation(string $agentId, string $sessionId)`: Procura uma conversa pelo `session_id` e `agent_id` ou cria uma nova com status `active_bot`.
  - `getConversation(string $id)`: Retorna uma conversa por ID.
  - `getMessages(string $conversationId)`: Retorna todas as mensagens associadas a uma conversa ordenadas por `created_at`.
  - `createMessage(array $data)`: Insere uma nova mensagem no banco (UUID automático).

### 2.3. services/HandoverService.php
- **Responsabilidades**:
  - `requestHandover(string $conversationId)`: Altera o status da conversa para `pending_operator` (ou `handover_requested`).
  - `assignOperator(string $conversationId, string $operatorId)`: Associa um operador à conversa e muda o status para `active_operator` (ou `active_human`).
  - `unassignOperator(string $conversationId)`: Liberta a conversa do operador, retornando o status para `pending_operator`.
  - `resolveConversation(string $conversationId)`: Marca a conversa como resolvida (`status = resolved`) e remove a atribuição do operador.

### 2.4. services/IntentService.php
- **Responsabilidades**:
  - `detectIntent(string $provider, string $model, string $latestMessage, array $history = [])`: Monta um prompt para classificar a intenção da última mensagem do utilizador (`human_handover`, `frustration` ou `general`) e usa o `LLMRouter` para fazer a classificação síncrona.

### 2.5. services/MailService.php
- **Responsabilidades**:
  - `sendMail(string $to, string $subject, string $body)`: Envia um email via SMTP utilizando a biblioteca manual PHPMailer carregada a partir de `libs/PHPMailer`. As configurações são lidas do `.env` ou settings.

### 2.6. services/MemoryService.php
- **Responsabilidades**:
  - `getMemory(string $sessionId, string $key)`: Obtém memória do utilizador.
  - `setMemory(string $sessionId, string $key, string $value)`: Salva ou atualiza a memória.
  - `injectMemory(string $systemPrompt, string $sessionId)`: Busca todas as memórias da sessão e injeta-as de forma estruturada no final do `systemPrompt`.

---

## 3. Endpoints da API (api/)

Estes ficheiros conterão as closures que manipulam as rotas HTTP e chamam os respetivos serviços.

### 3.1. api/auth.php
- `POST /api/auth/register` (Registo)
- `POST /api/auth/login` (Login)
- `POST /api/auth/2fa/setup` (Setup do 2FA - requer autenticação JWT)
- `POST /api/auth/2fa/verify` (Verificação do 2FA - requer autenticação JWT)

### 3.2. api/agents.php (Requer Admin)
- `GET /api/agents` (Listar todos)
- `GET /api/agents/:id` (Obter detalhe)
- `POST /api/agents` (Criar)
- `PUT /api/agents/:id` (Atualizar)
- `DELETE /api/agents/:id` (Eliminar)

### 3.3. api/conversations.php (Requer Autenticação)
- `GET /api/conversations` (Listar conversas filtradas por `sessionId` e/ou `status`)
- `GET /api/conversations/:id` (Detalhe da conversa)
- `GET /api/conversations/:id/messages` (Mensagens da conversa)

### 3.4. api/handover.php (Requer Autenticação)
- `POST /api/handover/:conversationId/request` (Solicitar transferência)
- `POST /api/handover/:conversationId/assign` (Atribuir conversa ao operador autenticado)
- `POST /api/handover/:conversationId/reply` (Operador responde à conversa)
- `POST /api/handover/:conversationId/resolve` (Marcar conversa como resolvida)

### 3.5. api/settings.php (Requer Admin)
- `GET /api/settings` (Retorna todas as configurações desencriptadas)
- `POST /api/settings` (Salva as configurações encriptando-as na base de dados)

### 3.6. api/polling.php (Requer Autenticação)
- `GET /api/polling/conversations` (Retorna conversas atualizadas desde o timestamp fornecido)
- `GET /api/polling/messages` (Retorna mensagens de uma conversa recebidas após determinado ID ou timestamp)

---

## 4. Integração (index.php)

- Modificação para requerer as rotas declaradas nos ficheiros `api/*.php`.
- Tratamento correto de exceções globais e retornos em JSON.
