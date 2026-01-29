DROP DATABASE product_db;
CREATE DATABASE product_db;
USE product_db;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    image_url VARCHAR(255)
);

INSERT INTO products (name, description, price, image_url) VALUES
('Caramel Syrup', 'Sweet caramel syrup perfect for coffee and desserts.', 10.99, 'https://www.longbeachsyrup.com/images/caramel.png'),
('Classic Caramel', 'Smooth and rich classic caramel flavor.', 12.50, 'https://img.boutirapp.com/i/TYlpJPjjjVfqC8Ef8PfUVrddrRdX68Jb4VegiCMk4WJ'),
('Vanilla Syrup', 'Pure vanilla syrup for delicious beverages.', 9.99, 'https://i.ibb.co/qLz4tFkP/412451.png'),
('Strawberry Syrup', 'Fruity strawberry syrup for milkshakes and teas.', 8.50, 'https://i.ibb.co/RG5bvqy7/412443.png'),
('Peach Syrup', 'Refreshing peach syrup with natural fruit taste.', 11.75, 'https://www.longbeachsyrup.com/images/peach%20740ml.png');
