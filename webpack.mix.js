const mix = require('laravel-mix');

mix.setPublicPath('public'); // dónde se compilan los archivos

mix.js('resources/js/app.jsx', 'js')
   .react()
   .postCss('resources/css/app.css', 'css', [
       require('autoprefixer')({
           overrideBrowserslist: ['> 1%', 'last 2 versions', 'not dead']
       })
   ])
   .sourceMaps()
   .version();