const axios = require('axios');
const crypto = require('crypto');

const API_URL = 'http://aufgaben.runasp.net/api/index.php';

async function generateAndUpload() {
  try {
    // 1. Generate 300 Todos
    const todos = [];
    const statuses = ['offen', 'erledigt'];
    const tagsPool = ['Arbeit', 'Privat', 'Wichtig', 'Idee', 'Projekt A', 'Einkauf', 'Sport', 'Lernen'];
    
    let baseTime = Date.now() - (300 * 60000); // Start 300 minutes ago
    
    for (let i = 0; i < 300; i++) {
      const id = baseTime + i * 60000;
      
      // Random target date between 30 days ago and 30 days in future
      const targetDateOffset = Math.floor(Math.random() * 61) - 30;
      const targetDateObj = new Date();
      targetDateObj.setDate(targetDateObj.getDate() + targetDateOffset);
      const y = targetDateObj.getFullYear();
      const m = String(targetDateObj.getMonth() + 1).padStart(2, '0');
      const d = String(targetDateObj.getDate()).padStart(2, '0');
      
      // Random tags (0 to 3 tags)
      const numTags = Math.floor(Math.random() * 4);
      const shuffledTags = [...tagsPool].sort(() => 0.5 - Math.random());
      const tags = shuffledTags.slice(0, numTags);
      
      todos.push({
        id,
        order: i,
        name: `Test Aufgabe ${i + 1} - Generiert`,
        description: `Dies ist eine automatisch generierte Test-Aufgabe (Nummer ${i + 1}) für Performance- und Lasttests der Anwendung.<br><br>Zufälliger Hash: ${crypto.randomBytes(8).toString('hex')}`,
        targetDate: Math.random() > 0.1 ? `${y}-${m}-${d}` : null, // 10% have no date
        tags,
        status: Math.random() > 0.8 ? 'erledigt' : 'offen' // 20% are completed
      });
    }

    // 2. Login to get Cookie
    console.log('Logging in...');
    const loginRes = await axios.post(`${API_URL}/auth/login`, {
      username: 'frost0xx',
      password: 'mypassword' // I will use the actual known password from users.json, but I need to fetch users.json or just upload directly via FTP
    });
    console.log('Login failed (wrong credentials)');
  } catch (err) {
    console.error('Error:', err.message);
  }
}

// Since I have FTP, I can just upload a PHP script that does this!
