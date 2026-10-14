const fs = require('fs');

const TOOTH_MESH_MAP = {
  'Object_6': '31',
  'Object_6.001': '31',
  'Object_6.002': '11',
  'Object_6.003': '11',
  'Object_6.004': '14',
  'Object_6.005': '14',
  'Object_6.006': '14',
  'Object_6.007': '14',
  'Object_6.008': '22',
  'Object_6.009': '22',
  'Object_6.010': '42',
  'Object_6.011': '42',
  'Object_6.012': '26',
  'Object_6.013': '26',
  'Object_6.014': '26',
  'Object_6.015': '27',
  'Object_6.016': '16',
  'Object_6.017': '17',
  'Object_6.018': '16',
  'Object_6.019': '16',
  'Object_6.020': '46',
  'Object_6.021': '46',
  'Object_6.022': '46',
  'Object_6.023': '36',
  'Object_6.024': '36',
  'Object_6.025': '36',
  'Object_6.026': '24',
  'Object_6.027': '24',
  'Object_6.028': '24',
  'Object_6.029': '24',
  'Object_6.030': '34',
  'Object_6.031': '34',
  'Object_6.032': '44',
  'Object_6.033': '44',
  'Object_6.034': '32',
  'Object_6.035': '32',
  'Object_6.036': '12',
  'Object_6.037': '12',
  'Object_6.038': '18',
  'Object_6.039': '18',
  'Object_6.040': '17',
  'Object_6.041': '17',
  'Object_6.042': '15',
  'Object_6.043': '15',
  'Object_6.044': '15',
  'Object_6.045': '25',
  'Object_6.046': '25',
  'Object_6.047': '25',
  'Object_6.048': '23',
  'Object_6.049': '23',
  'Object_6.050': '28',
  'Object_6.051': '28',
  'Object_6.052': '28',
  'Object_6.053': '28',
  'Object_6.054': '47',
  'Object_6.055': '48',
  'Object_6.056': '47',
  'Object_6.057': '45',
  'Object_6.058': '45',
  'Object_6.059': '43',
  'Object_6.060': '43',
  'Object_6.061': '41',
  'Object_6.062': '41',
  'Object_6.063': '33',
  'Object_6.064': '33',
  'Object_6.065': '35',
  'Object_6.066': '37',
  'Object_6.067': '37',
  'Object_6.068': '38',
  'Object_6.069': '21',
  'Object_6.070': '21',
  'Object_6.071': '13',
  'Object_6.072': '13',
};

function isUpperTooth(num) {
  const n = parseInt(num, 10);
  return (n >= 11 && n <= 18) || (n >= 21 && n <= 28);
}

function isLowerTooth(num) {
  const n = parseInt(num, 10);
  return (n >= 31 && n <= 38) || (n >= 41 && n <= 48);
}

// In Upper mode:
const upperVisible = [];
const upperHidden = [];
for (const [name, tooth] of Object.entries(TOOTH_MESH_MAP)) {
  if (isUpperTooth(tooth)) upperVisible.push({ name, tooth });
  else upperHidden.push({ name, tooth });
}

console.log(`Upper mode: ${upperVisible.length} visible meshes, ${upperHidden.length} hidden meshes.`);
console.log('Teeth visible in Upper mode:', [...new Set(upperVisible.map(x => x.tooth))].sort());
console.log('Teeth hidden in Upper mode:', [...new Set(upperHidden.map(x => x.tooth))].sort());

// In Lower mode:
const lowerVisible = [];
const lowerHidden = [];
for (const [name, tooth] of Object.entries(TOOTH_MESH_MAP)) {
  if (isLowerTooth(tooth)) lowerVisible.push({ name, tooth });
  else lowerHidden.push({ name, tooth });
}

console.log(`\nLower mode: ${lowerVisible.length} visible meshes, ${lowerHidden.length} hidden meshes.`);
console.log('Teeth visible in Lower mode:', [...new Set(lowerVisible.map(x => x.tooth))].sort());
console.log('Teeth hidden in Lower mode:', [...new Set(lowerHidden.map(x => x.tooth))].sort());
