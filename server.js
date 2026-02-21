const express = require('express');
const fs = require('fs');
const path = require('path');
const app = express();
const PORT = process.env.PORT || 3000;

// Zvýšení limitu pro JSON payload (kvůli base64 obrázkům z adminu)
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ limit: '10mb', extended: true }));

// Middleware pro obsluhu statických souborů z root složky
app.use(express.static(__dirname));

const dataFilePath = path.join(__dirname, 'data.json');

// Endpoint pro získání dat z JSONu
app.get('/api/data', (req, res) => {
    fs.readFile(dataFilePath, 'utf8', (err, data) => {
        if (err) {
            console.error('Error reading file:', err);
            return res.status(500).json({ error: 'Internal Server Error' });
        }
        res.json(JSON.parse(data));
    });
});

// Endpoint pro uložení dat z Adminu
app.post('/api/data', (req, res) => {
    const newData = req.body;
    
    // validace, že nám něco vůbec přišlo
    if (!newData) {
        return res.status(400).json({ error: 'No data provided' });
    }

    fs.writeFile(dataFilePath, JSON.stringify(newData, null, 2), 'utf8', (err) => {
        if (err) {
            console.error('Error writing file:', err);
            return res.status(500).json({ error: 'Failed to save data' });
        }
        res.json({ success: true, message: 'Data saved successfully' });
    });
});

app.listen(PORT, () => {
    console.log(`Server is running on http://localhost:${PORT}`);
});