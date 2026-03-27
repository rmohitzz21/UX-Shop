const fs = require('fs');
const path = require('path');

function replaceInDir(dir) {
  const files = fs.readdirSync(dir);
  for (const file of files) {
    const fullPath = path.join(dir, file);
    if (fs.statSync(fullPath).isDirectory()) {
      if (!['node_modules', '.git', 'img'].includes(file)) {
        replaceInDir(fullPath);
      }
    } else if (['.php', '.html', '.css', '.js'].includes(path.extname(fullPath))) {
      let content = fs.readFileSync(fullPath, 'utf8');
      
      // Update logic to replace img/logo1.webp or img/logo1.webp with img/logo1.webp
      let newContent = content.replace(/img\/(?:logo\.webp|LOGO\.webp|dark-logo\.webp|logo\.png|LOGO\.png)/g, 'img/logo1.webp');
      newContent = newContent.replace(/url\(['"]?img\/(?:logo\.webp|LOGO\.webp|dark-logo\.webp|logo\.png|LOGO\.png)['"]?\)/g, "url('img/logo1.webp')");

      if (content !== newContent) {
        fs.writeFileSync(fullPath, newContent);
        console.log('Updated ' + fullPath);
      }
    }
  }
}

replaceInDir('.');
console.log('Replacement complete.');
