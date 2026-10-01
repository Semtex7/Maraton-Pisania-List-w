const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const browserSync = require('browser-sync').create();
const concat = require('gulp-concat');
const cleanCSS = require('gulp-clean-css');
const fs = require('fs');
const terser = require('gulp-terser');
const sourcemaps = require('gulp-sourcemaps');

// const rename = require('gulp-rename');

const distPath = __dirname + '/dist/';
const sourcePath = __dirname + '/source/';

const sassOptions = {
  errLogToConsole: true,
  outputStyle: 'expanded'
};

// front.scss
gulp.task('sass-front', function () {
  if (fs.existsSync(sourcePath + 'scss')) { // Check for SCSS directory
    return gulp.src(sourcePath + 'scss/front.scss')
      .pipe(sourcemaps.init())
      .pipe(sass(sassOptions).on('error', sass.logError))
      .pipe(concat('front.min.css'))
      .pipe(cleanCSS())
      .pipe(sourcemaps.write())
      .pipe(gulp.dest(distPath))
      .pipe(browserSync.reload({ stream: true }));
  }
  return Promise.resolve(); // Skip task if directory doesn't exist
});

// admin.scss
gulp.task('sass-admin', function () {
  if (fs.existsSync(sourcePath + 'scss')) { // Check for SCSS directory
    return gulp.src(sourcePath + 'scss/admin.scss')
      .pipe(sourcemaps.init())
      .pipe(sass(sassOptions).on('error', sass.logError))
      .pipe(concat('admin.min.css'))
      .pipe(cleanCSS())
      .pipe(sourcemaps.write())
      .pipe(gulp.dest(distPath))
      .pipe(browserSync.reload({ stream: true }));
  }
  return Promise.resolve(); // Skip task if directory doesn't exist
});

// JS front.js
gulp.task('scripts-front', function () {
  if (fs.existsSync(sourcePath + 'js')) { // Check for JS directory
    return gulp.src(sourcePath + 'js/para.js')
      .pipe(concat('para.min.js'))
      .pipe(terser()) // Minify the JS
      .pipe(sourcemaps.write())
      .pipe(gulp.dest(distPath))
      .pipe(browserSync.reload({ stream: true }));
  }
  return Promise.resolve(); // Skip task if directory doesn't exist
});

// JS admin.js
gulp.task('scripts-admin', function () {
  if (fs.existsSync(sourcePath + 'js')) { // Check for JS directory
    return gulp.src(sourcePath + 'js/paraadmin.js')
      .pipe(concat('paraadmin.min.js'))
      .pipe(terser()) // Minify the JS
      .pipe(sourcemaps.write())
      .pipe(gulp.dest(distPath))
      .pipe(browserSync.reload({ stream: true }));
  }
  return Promise.resolve(); // Skip task if directory doesn't exist
});

// Fonts and Images (assets) task
gulp.task('assets', function () {
  if (fs.existsSync(distPath + 'assets')) { // Check for Fonts directory
    return gulp.src(distPath + 'assets/**/*.*')
      .pipe(browserSync.reload({ stream: true }));
  }
  return Promise.resolve(); // Skip task if directory doesn't exist
});

// BrowserSync task
gulp.task('browser-sync', function () {
  browserSync.init({
    proxy: 'maraton-pisania-listow.local',
    host: 'maraton-pisania-listow.local',
    open: 'external',
    notify: false // Optional: Prevents BrowserSync from showing notifications
  });
});

// Watch task
gulp.task('watch', function () {
  gulp.watch(sourcePath + "scss/**/*.*", gulp.series('sass-front'));
  gulp.watch(sourcePath + "scss/**/*.*", gulp.series('sass-admin'));
  gulp.watch(sourcePath + "js/**/*.*", gulp.series('scripts-front'));
  gulp.watch(sourcePath + "js/**/*.*", gulp.series('scripts-admin'));
  gulp.watch(distPath + "assets", gulp.series('assets'));
});

gulp.task('build', gulp.parallel(
  'sass-front',
  'sass-admin',
  'scripts-front',
  'scripts-admin',
  'assets',
));

// Default task
gulp.task('default', gulp.parallel(
  'build',
  'browser-sync',
  'watch'
));
