const fs = require('fs');

const buf = fs.readFileSync('public/models/teeth-seperated-optimized.glb');
const jsonLen = buf.readUInt32LE(12);
const jsonStr = buf.toString('utf8', 20, 20 + jsonLen);
const gltf = JSON.parse(jsonStr);

console.log('Scene roots:', gltf.scenes[gltf.scene || 0].nodes);
gltf.nodes.forEach((n, idx) => {
  if (n.children && n.children.length > 0) {
    console.log(`Node [${idx}] "${n.name}" has children:`, n.children.map(c => `[${c}] ${gltf.nodes[c].name}`).join(', '));
  }
});
