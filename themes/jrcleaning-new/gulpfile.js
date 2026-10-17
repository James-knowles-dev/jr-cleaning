// Compiles app/scss -> main.css and concatenates app/js -> script.js
const { src, dest, watch, series, parallel } = require('gulp');
const sourcemaps = require('gulp-sourcemaps');
const sassGlob = require('gulp-sass-glob');
const sass = require('gulp-sass')(require('sass'));
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

const files = {
    // Six bundles, each loaded only where it applies (see functions/enqueue.php):
    //
    //   main.css      every page — base, chrome, marketing blocks
    //   app.css       shop + My Account       jrc_needs_woo()
    //   partners.css  partner page templates  jrc_needs_partners()
    //   forms.css     pages with a form       jrc_needs_forms()
    //   areas.css     page-areas.php          jrc_needs_areas()
    //   quote.css     page-quote.php          jrc_needs_quote()
    //
    // All compile with the same pipeline; listing one here is all it takes to add it.
    scssPath: [
        'app/scss/main.scss',
        'app/scss/app.scss',
        'app/scss/partners.scss',
        'app/scss/forms.scss',
        'app/scss/areas.scss',
        'app/scss/quote.scss'
    ],
    jsPath: 'app/js/**/*.js'
};

// `gulp build` (what the deploy runs) emits no sourcemap: main.css.map is excluded
// from rsync, so shipping the sourceMappingURL comment just produces a 404 on
// staging/production every time someone opens DevTools. `gulp` (watch) keeps them.
function buildScss(withMaps) {
    return function scssTask() {
        let stream = src(files.scssPath);
        if (withMaps) { stream = stream.pipe(sourcemaps.init()); }
        stream = stream
            .pipe(sassGlob())
            .pipe(sass().on('error', sass.logError))
            .pipe(postcss([autoprefixer(), cssnano()]));
        if (withMaps) { stream = stream.pipe(sourcemaps.write('.')); }
        return stream.pipe(dest('./'));
    };
}

const scssTask = buildScss(false);
const scssTaskDev = buildScss(true);

// Without a handler a syntax error in any app/js file throws out of the stream and
// kills the whole watcher — silently, from the author's point of view, because the
// terminal is scrolled away and the browser just keeps serving the last good build.
// Log it, emit 'end' so the stream closes cleanly, and let the watch carry on.
function onError(err) {
    console.error('\n[js] ' + (err.message || err) + '\n');
    this.emit('end');
}

function jsTask() {
    return src([files.jsPath])
        .pipe(concat('script.js'))
        .pipe(uglify().on('error', onError))
        .pipe(dest('./'));
}

function watchTask() {
    watch(['app/scss/**/*.scss', files.jsPath], parallel(scssTaskDev, jsTask));
}

exports.build = parallel(scssTask, jsTask);
exports.default = series(parallel(scssTaskDev, jsTask), watchTask);
