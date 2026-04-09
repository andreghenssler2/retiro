CREATE TABLE titulo(
    idTitulo INT NOT NULL AUTO_INCREMENT,
    PRIMARY KEY(idTitulo),
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(250),
    keyword TEXT,
    favicon VARCHAR(250),
    imagem VARCHAR(250)
);

CREATE TABLE cidade(
    idCidade INT not null AUTO_INCREMENT,
    PRIMARY key(idCidade),
    nomeCidade varchar(67) not null,
    UF char(6)
);
INSERT INTO cidade (idCidade, nomeCidade, UF) VALUES (1, 'Parobé', 'RS'), (2, 'Taquara', 'RS');

CREATE TABLE importancia(
    idImportancia int not null AUTO_INCREMENT,
    PRIMARY key(idImportancia),
    tipo char(50) not null
);
INSERT INTO importancia (idImportancia, tipo) VALUES (NULL, 'Baixa'), (NULL, 'Média'), (NULL, 'Alta');

CREATE TABLE evento(
    idEvento int not null AUTO_INCREMENT,
    PRIMARY KEY (idEvento),
    nomeEvento VARCHAR (250) not null,
    dataInicio date not null,
    horaInicio time,
    dataFinal date not null,
    horaFinal time,
    descricao varchar(500),
    imagem varchar(120),
    codImpot int not null DEFAULT 1,
    CONSTRAINT `fk_importancia_e` FOREIGN KEY (`idEvento`) REFERENCES `importancia`(`idImportancia`)
);  

CREATE TABLE localizacao(
    idLocal int not null AUTO_INCREMENT,
    PRIMARY KEY(idLocal),
    codEvento int not null,
    endereceo varchar (120),
    numero char(11),
    complemento char(11),
    codCidade int,
    CONSTRAINT fk_cidade FOREIGN KEY (codCidade) references cidade(idCidade) ,
    CONSTRAINT fk_evento FOREIGN KEY (codEvento) references evento(idEvento) 
);

CREATE TABLE valor_inscricao(
    idValor int NOT null AUTO_INCREMENT,
    PRIMARY key(idValor),
    codEventov int NOT null,
    CONSTRAINT fk_eventov FOREIGN KEY (codEventov) references evento(idEvento),
    taxaInscricao decimal(10,2) NOT NULL DEFAULT 0.00
);


CREATE TABLE acesso(
    idAcess int not null AUTO_INCREMENT,
    PRIMARY key (idAcess),
    tipo_acess char(20)
);

INSERT INTO acesso (idAcess, tipo_acess) VALUES ('1', 'Administrador'),('2', 'Coloborador'),('3', 'Secretário (a)'),('4', 'Participante'),('5', 'Palestrante'),('6', 'Visitante'),('7','Tesoureiro'),('8', 'Presidente'),('9', 'Vice-Presidente'),('10', 'Membro');


CREATE TABLE users(
    idUser int not null AUTO_INCREMENT,
    PRIMARY key(idUser),
    user char(64) not null unique,
    senha char(120) not null,
    nome varchar(250) not null,
    email varchar(250) not null unique,
    cpf char(20) not null unique,
    codAcess int not null DEFAULT 3,
    dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_acess FOREIGN key (codAcess) references acesso(idAcess)
);

create table sexo(
    idSexo int not null AUTO_INCREMENT,
    PRIMARY key(idSexo),
    tipo char(20) not null
);
insert into sexo (idSexo, tipo) values (1, 'Masculino'), (2, 'Feminino'), (3, 'Outro');

CREATE TABLE participante(
    idParticipante INT NOT NULL AUTO_INCREMENT,
    PRIMARY KEY(idParticipante),
    codUser INT NOT NULL,
    CONSTRAINT fk_user FOREIGN KEY(codUser) REFERENCES users(idUser),
    cpf CHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(250) NOT NULL,
    email VARCHAR(250) NOT NULL UNIQUE,
    telefone CHAR(20),
    celular CHAR(20) NOT NULL,
    dataNascimento DATE NOT NULL,
    codSexo int NOT NULL,
    CONSTRAINT fk_sexo FOREIGN KEY(codSexo) REFERENCES sexo(idSexo)
);
create table palestrante(
    idPalestrante int not null AUTO_INCREMENT,
    PRIMARY key(idPalestrante),
    codUser int not null,
    CONSTRAINT fk_userp FOREIGN KEY (codUser) references users(idUser),

    cpf char(20) not null unique,
    nome varchar(250) not null,
    email varchar(250) not null unique,
    telefone char(20),
    celular char(20) not null,
    dataNascimento date not null,
    codSexo int not null,
    CONSTRAINT fk_sexop FOREIGN KEY (codSexo) references sexo(idSexo)

);

create table eventos_participante(
    idEventoPart int not null AUTO_INCREMENT,
    PRIMARY key(idEventoPart),
    codParticipante int not null,
    codEvento int not null,
    CONSTRAINT fk_participante FOREIGN KEY (codParticipante) references participante(idParticipante),
    CONSTRAINT fk_eventop FOREIGN KEY (codEvento) references evento(idEvento),
    constraint fk_eventoPalestrante FOREIGN KEY (codParticipante) references palestrante(idPalestrante)
);