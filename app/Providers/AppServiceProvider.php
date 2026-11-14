<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Site online com https (APP_URL começa com https://): os links de CSS e JS também saem em https,
        // senão o navegador bloqueia como "conteúdo misto" e a página fica sem estilo.
        // Enquanto o SSL da hospedagem não estiver ativo, APP_URL fica com http:// e nada é forçado.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        /**
         * @assetVersionado('css/app.css') gera "/css/app.css?v=<data de modificação do arquivo>".
         * Quando o arquivo muda, a URL muda e o navegador baixa a versão nova em vez de usar o cache antigo.
         */
        Blade::directive('assetVersionado', function (string $caminho): string {
            return "<?php echo e(asset({$caminho}).'?v='.@filemtime(public_path({$caminho}))); ?>";
        });
    }
}
