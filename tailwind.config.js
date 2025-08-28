/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{astro,html,js,jsx,ts,tsx,vue,svelte}"
  ],
 theme: {
    extend: {
      colors: {
        finca: {
          50:'#f1f2ee',100:'#e5e7df',200:'#d3d6c9',300:'#b9bea9',400:'#9fa588',
          500:'#505c27',600:'#444e21',700:'#343c19',800:'#242912',900:'#14170a'
        },
        accent: {
          50:'#f3fafd',100:'#e9f6fb',200:'#dbf0f8',300:'#c5e6f4',400:'#afddf0',
          500:'#6ec1e4',600:'#5ea4c2',700:'#487d94',800:'#315767',900:'#1c3039'
        },
        neutralish: {
          50:'#f1f2f2',100:'#e6e6e7',200:'#d4d6d7',300:'#bbbdbf',400:'#a2a4a8',
          500:'#555960',600:'#484c52',700:'#373a3e',800:'#26282b',900:'#151618'
        }
      }
    }
  },
  plugins: [],
}
