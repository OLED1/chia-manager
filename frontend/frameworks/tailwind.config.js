const colors = require('tailwindcss/colors')

module.exports = {
  content: ["/var/www/dev.chiamgmt.edtmair.at/frontend/*.{html,js,php}"],
  theme: {
    extend: {},
  },
  plugins: [
    require('tailwindcss'),
    //require('autoprefixer'),
  ],
}
