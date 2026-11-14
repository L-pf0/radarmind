// public/js/filtro-reposicao.js
// Tela de Estoque > "Pedidos de reposição em aberto": filtro por unidade feito com Vue 3.
// Os pedidos vêm prontos do Laravel (atributo data-pedidos); o Vue só escolhe quais mostrar,
// sem chamar o servidor e sem alterar nenhum dado.

(function () {
    const raiz = document.getElementById('filtroReposicao');

    // Sem a seção ou sem o Vue (CDN fora do ar), a tabela feita pelo Blade continua na tela
    if (!raiz || !window.Vue) return;

    const pedidos = JSON.parse(raiz.dataset.pedidos || '[]');

    Vue.createApp({
        data() {
            return {
                pedidos: pedidos,
                unidadeSelecionada: '', // '' = todas as unidades
            };
        },

        computed: {
            // Lista de unidades que têm pedido, sem repetir, em ordem alfabética
            unidades() {
                return [...new Set(this.pedidos.map((pedido) => pedido.unidade))]
                    .sort((a, b) => a.localeCompare(b, 'pt-BR'));
            },

            // Só os pedidos da unidade escolhida (ou todos)
            pedidosFiltrados() {
                if (this.unidadeSelecionada === '') {
                    return this.pedidos;
                }

                return this.pedidos.filter((pedido) => pedido.unidade === this.unidadeSelecionada);
            },

            textoContador() {
                const total = this.pedidosFiltrados.length;
                return total + (total === 1 ? ' solicitação' : ' solicitações');
            },
        },

        methods: {
            contarPorUnidade(unidade) {
                return this.pedidos.filter((pedido) => pedido.unidade === unidade).length;
            },
        },

        // Mesmas classes da tabela original (.rm-stack + data-label = cartões no celular)
        template: `
            <div v-if="unidades.length > 1" class="rm-filtro-unidade">
                <label for="filtroUnidade">Mostrar solicitações da unidade</label>
                <div class="rm-filtro-unidade-linha">
                    <select id="filtroUnidade" v-model="unidadeSelecionada">
                        <option value="">Todas as unidades ({{ pedidos.length }})</option>
                        <option v-for="unidade in unidades" :key="unidade" :value="unidade">
                            {{ unidade }} ({{ contarPorUnidade(unidade) }})
                        </option>
                    </select>
                    <span class="badge off" aria-live="polite">{{ textoContador }}</span>
                    <button v-if="unidadeSelecionada !== ''" type="button" class="link" @click="unidadeSelecionada = ''">
                        Limpar filtro
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="rm-stack">
                    <thead>
                        <tr>
                            <th>Unidade</th><th>Item</th><th>Quantidade</th><th>Data</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pedido in pedidosFiltrados" :key="pedido.id">
                            <td data-label="Unidade">{{ pedido.unidade }}</td>
                            <td data-label="Item">{{ pedido.item }}</td>
                            <td data-label="Quantidade">{{ pedido.quantidade }}</td>
                            <td data-label="Data">{{ pedido.data }}</td>
                            <td data-label="Status"><span class="badge low">{{ pedido.status }}</span></td>
                            <td data-label="">
                                <a v-if="pedido.url_confirmar" class="link" :href="pedido.url_confirmar"
                                    title="Abre a confirmação de chegada com a assinatura de recebimento">Confirmar chegada</a>
                            </td>
                        </tr>
                        <tr v-if="pedidosFiltrados.length === 0">
                            <td colspan="6" class="empty">Nenhum pedido em aberto.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `,
    }).mount(raiz);
})();
