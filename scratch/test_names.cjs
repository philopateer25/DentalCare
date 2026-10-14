const fs = require('fs');

// Inspect names in GLTF json
const buf = fs.readFileSync('public/models/teeth-seperated-optimized.glb');
const jsonLen = buf.readUInt32LE(12);
const jsonStr = buf.toString('utf8', 20, 20 + jsonLen);
const gltf = JSON.parse(jsonStr);

console.log('Nodes count:', gltf.nodes.length);
console.log('Meshes count:', gltf.meshes.length);
gltf.nodes.forEach((n, idx) => {
  const mesh = n.mesh !== undefined ? gltf.meshes[n.mesh] : null;
  console.log(`Node [${idx}] name="${n.name}", meshIndex=${n.mesh}, meshName="${mesh ? mesh.name : ''}"`);
});
