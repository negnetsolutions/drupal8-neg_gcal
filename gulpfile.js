var gulp         = require("gulp"),
    sass         = require("gulp-sass"),
    autoprefixer = require("gulp-autoprefixer"),
    cleanCSS    =  require('gulp-clean-css'),
    exec = require('child_process').exec,
    util = require('gulp-util')
    ;

var config = {
  jsPattern: 'js/**/*.js',
  sassPattern: 'scss/**/*.*',
  cssPath: 'css',
  dev: !!util.env.dev
};

// Compile SCSS files to CSS
gulp.task("scss", function (done) {
  var stream = gulp.src(config.sassPattern)
    .pipe(sass({
      outputStyle : config.dev ? "expanded" : "compressed"
    }))
    .pipe(autoprefixer({
      // grid: true,
      // browsers : ["last 2 versions", 'ie 6-8', 'Firefox > 20']
      browsers : ["last 2 versions", 'Firefox > 20']
    }))
    .pipe(config.dev ? util.noop() : cleanCSS({compatibility: '*'}))
    .pipe(gulp.dest(config.cssPath))
  ;

  return stream;
});

// Watch asset folder for changes
gulp.task("watch", gulp.series("scss", function () {
    gulp.watch("scss/**/*").on('change', gulp.series("scss"));
}));

gulp.task("default", gulp.series("watch", function (done) { done(); }));
