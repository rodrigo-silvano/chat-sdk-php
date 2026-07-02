# Chat SDK PHP — Guia de Instalação em Alojamento Partilhado cPanel

Este guia detalha o processo passo-a-passo para instalar o Chat SDK PHP num servidor com alojamento partilhado gerido via cPanel. O Chat SDK PHP foi concebido em PHP 8 puro, sem recurso a frameworks, Composer ou Docker, facilitando a sua execução em ambientes de alojamento tradicionais.

---

## Requisitos do Servidor
* PHP 8.0 ou superior
* Extensão PDO MySQL ativada no PHP
* Servidor Web Apache com suporte para ficheiros `.htaccess` e módulo `mod_rewrite` ativo

---

## Passo 1: Configurar a Base de Dados no cPanel
Como os ambientes de alojamento partilhado restringem a criação direta de bases de dados via scripts PHP, deve criar a base de dados previamente através do cPanel:

1. Aceda ao painel do seu cPanel.
2. Na secção **Bases de Dados**, clique em **Bases de Dados MySQL** (MySQL Databases).
3. Em **Criar Nova Base de Dados**, introduza o nome desejado (por exemplo, `meusite_chat`) e clique em **Criar Base de Dados**.
4. Em **Utilizadores MySQL -> Adicionar Novo Utilizador**, introduza um nome de utilizador (por exemplo, `meusite_chatuser`), gere uma palavra-passe segura e guarde-a. Clique em **Criar Utilizador**.
5. Em **Adicionar Utilizador à Base de Dados**, selecione o utilizador e a base de dados criados e clique em **Adicionar**.
6. Na janela de privilégios, selecione a opção **Todos os Privilégios** (ALL PRIVILEGES) e clique em **Fazer Alterações**.

---

## Passo 2: Upload dos Ficheiros do Projeto
1. Comprima todos os ficheiros da pasta do projeto `chat-sdk-php` num ficheiro ZIP (excluindo pastas locais de controlo de versão como `.git`, se desejar).
2. Aceda ao cPanel e clique em **Gestor de Ficheiros** (File Manager).
3. Navegue até à pasta pretendida onde deseja alojar o chat (por exemplo, a pasta raiz `public_html`, ou crie uma subpasta como `/public_html/chat/`).
4. Clique em **Carregar** (Upload), selecione o ficheiro ZIP e aguarde a conclusão.
5. Extraia o conteúdo do ficheiro ZIP na pasta de destino utilizando a ferramenta **Extrair** (Extract) do cPanel.

---

## Passo 3: Executar o Assistente de Instalação (Wizard)
1. Abra o seu browser e aceda ao endereço correspondente à pasta onde extraiu o projeto com o sufixo `/install/` (por exemplo: `https://o-seu-dominio.com/chat/install/`).
2. **Passo 1 — Base de Dados:**
   * **Servidor MySQL (Host):** Introduza o endereço do servidor (geralmente `localhost`).
   * **Nome da Base de Dados:** Introduza o nome completo da base de dados criada no cPanel (ex: `nomeusuario_chat`).
   * **Utilizador MySQL:** Introduza o nome completo do utilizador criado no cPanel (ex: `nomeusuario_chatuser`).
   * **Password MySQL:** Introduza a palavra-passe que definiu para o utilizador MySQL.
   * Clique em **Ligar e Configurar**. O instalador criará as tabelas da base de dados.
3. **Passo 2 — Administrador:**
   * **Nome do Administrador:** Defina o nome do operador principal (ex: `Admin`).
   * **Email do Administrador:** Introduza o e-mail de acesso para o painel de administração.
   * **Password do Administrador:** Defina uma palavra-passe de acesso segura (mínimo de 8 caracteres).
   * **Confirmar Password:** Confirme a palavra-passe introduzida.
   * **Instalar dados de teste (Seeding):** Ative esta opção caso pretenda testar a aplicação imediatamente com configurações padrão e conversas de demonstração na base de dados.
   * Clique em **Finalizar Instalação**. O instalador gerará os ficheiros de configuração `config/database.php` e `config/app.php` com chaves de encriptação e tokens JWT gerados aleatoriamente de forma segura.

---

## Passo 4: Limpeza e Segurança Pós-Instalação
Após visualizar a mensagem de instalação concluída com sucesso, execute as seguintes ações de segurança:

1. Aceda ao **Gestor de Ficheiros** do cPanel.
2. Navegue até ao diretório de instalação do chat.
3. **Elimine permanentemente a pasta `install/`** para evitar que terceiros possam reiniciar o assistente ou aceder a ficheiros confidenciais.

---

## Passo 5: Integração do Widget no Seu Site Cliente
Para ativar o widget de chat em qualquer página do seu website:

1. Adicione a seguinte linha de código HTML mesmo antes da tag de fecho `</body>` das suas páginas:
   ```html
   <script src="https://o-seu-dominio.com/chat/widget/loader.js" async></script>
   ```
   *(Substitua `https://o-seu-dominio.com/chat/` pelo URL absoluto onde instalou o Chat SDK PHP)*

2. O script `loader.js` encarrega-se de detetar e carregar de forma dinâmica e assíncrona o widget principal (`widget.js`), garantindo que o carregamento da sua página web não é afetado.
