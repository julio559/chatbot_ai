<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Etapas | CRM da Dra. Bruna Sathler</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">

    <!-- Container Principal -->
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-semibold text-gray-800 mb-6">Gerenciar Etapas</h1>

        <!-- Botão para abrir o Modal -->
        <button id="openModalButton" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 mb-6" onclick="openModal()">
            Adicionar Etapa
        </button>

        <!-- Tabela de Etapas -->
        <table class="min-w-full bg-white rounded-xl shadow-md">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left">Nome da Etapa</th>
                    <th class="px-6 py-3 text-left">Prompt</th>
                    <th class="px-6 py-3 text-left">Tempo de Resposta</th>
                    <th class="px-6 py-3 text-left">Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Loop para exibir as etapas cadastradas -->
                <?php foreach ($etapas as $etapa): ?>
                    <tr>
                        <!-- Usando a coluna correta 'etapa_base' -->
                        <td class="px-6 py-3"><?= esc($etapa['etapa_base']) ?></td> <!-- Altere para 'etapa_base' -->
                        <td class="px-6 py-3"><?= esc($etapa['prompt_base']) ?></td> <!-- Prompt -->
                        <td class="px-6 py-3"><?= esc($etapa['tempo_resposta']) ?> segundos</td> <!-- Tempo de resposta -->
                        <td class="px-6 py-3">
                            <!-- Botões de Editar e Excluir -->
                            <button class="text-blue-500 hover:text-blue-700" onclick="openEditModal('<?= esc($etapa['etapa_base']) ?>', '<?= esc($etapa['prompt_base']) ?>', '<?= esc($etapa['tempo_resposta']) ?>')">
                                Editar
                            </button>
                            <button class="text-red-500 hover:text-red-700 ml-2" onclick="deleteEtapa('<?= esc($etapa['etapa_base']) ?>')">
                                Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de Criação/Atualização -->
    <div id="modal" class="fixed inset-0 flex justify-center items-center bg-black bg-opacity-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96">
            <h2 class="text-xl font-semibold mb-4" id="modalTitle">Criar Etapa</h2>
            <form id="formModal">
                <div class="mb-4">
                    <label for="nome_etapa" class="block text-gray-700">Nome da Etapa</label>
                    <input type="text" id="nome_etapa" name="nome_etapa" class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>

                <div class="mb-4">
                    <label for="prompt" class="block text-gray-700">Prompt</label>
                    <textarea id="prompt" name="prompt" rows="4" class="w-full p-3 border border-gray-300 rounded-lg" required></textarea>
                </div>

                <div class="mb-4">
                    <label for="tempo_resposta" class="block text-gray-700">Tempo de Resposta (segundos)</label>
                    <input type="number" id="tempo_resposta" name="tempo_resposta" class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>

                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="modo_formal" name="modo_formal" class="mr-2">
                    <label for="modo_formal" class="text-gray-700">Modo Formal</label>
                </div>

                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="permite_respostas_longas" name="permite_respostas_longas" class="mr-2">
                    <label for="permite_respostas_longas" class="text-gray-700">Permite Respostas Longas</label>
                </div>

                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="permite_redirecionamento" name="permite_redirecionamento" class="mr-2">
                    <label for="permite_redirecionamento" class="text-gray-700">Permite Redirecionamento</label>
                </div>

                <div class="flex justify-end">
                    <button type="button" id="saveButton" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Salvar
                    </button>
                    <button type="button" id="cancelButton" class="ml-2 px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-700">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mostrar Modal
        function openModal() {
            document.getElementById("modal").classList.remove("hidden");
            document.getElementById("formModal").reset(); // Limpa o formulário
            document.getElementById("modalTitle").innerText = "Criar Etapa"; // Título do Modal
            document.getElementById("saveButton").innerText = "Salvar";
        }

        // Mostrar Modal para Editar
        function openEditModal(nome, prompt, tempo_resposta, modo_formal, permite_respostas_longas, permite_redirecionamento) {
            document.getElementById("modal").classList.remove("hidden");
            document.getElementById("modalTitle").innerText = "Editar Etapa";
            document.getElementById("saveButton").innerText = "Atualizar";

            // Preenche os campos com os valores da etapa
            document.getElementById("nome_etapa").value = nome;
            document.getElementById("prompt").value = prompt;
            document.getElementById("tempo_resposta").value = tempo_resposta;
            document.getElementById("modo_formal").checked = modo_formal;
            document.getElementById("permite_respostas_longas").checked = permite_respostas_longas;
            document.getElementById("permite_redirecionamento").checked = permite_redirecionamento;
        }

        // Fechar Modal
        document.getElementById("cancelButton").addEventListener("click", function() {
            document.getElementById("modal").classList.add("hidden");
        });

        // Função para salvar ou atualizar a etapa
        document.getElementById("saveButton").addEventListener("click", function() {
            const nome_etapa = document.getElementById("nome_etapa").value;
            const prompt = document.getElementById("prompt").value;
            const tempo_resposta = document.getElementById("tempo_resposta").value;
            const modo_formal = document.getElementById("modo_formal").checked;
            const permite_respostas_longas = document.getElementById("permite_respostas_longas").checked;
            const permite_redirecionamento = document.getElementById("permite_redirecionamento").checked;

            // Enviar dados via AJAX (em vez de alertar, aqui você faz a chamada AJAX para salvar/atualizar)
            $.ajax({
                url: '/etapa/criar_ou_atualizar',
                method: 'POST',
                data: {
                    nome_etapa,
                    prompt,
                    tempo_resposta,
                    modo_formal,
                    permite_respostas_longas,
                    permite_redirecionamento
                },
                success: function(response) {
                    alert("Etapa salva com sucesso!");
                    location.reload(); // Recarregar a página para ver a alteração
                },
                error: function() {
                    alert("Erro ao salvar a etapa!");
                }
            });

            document.getElementById("modal").classList.add("hidden");
        });

        // Função para excluir a etapa
        function deleteEtapa(nome) {
            if (confirm("Tem certeza que deseja excluir a etapa " + nome + "?")) {
                // Enviar requisição AJAX para excluir a etapa
                $.ajax({
                    url: '/etapa/excluir',
                    method: 'POST',
                    data: { nome_etapa: nome },
                    success: function(response) {
                        alert("Etapa excluída com sucesso!");
                        location.reload(); // Recarregar a página para ver a alteração
                    },
                    error: function() {
                        alert("Erro ao excluir a etapa!");
                    }
                });
            }
        }
    </script>
</body>
</html>
