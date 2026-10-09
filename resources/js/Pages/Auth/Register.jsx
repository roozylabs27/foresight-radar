import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Alert,
    Button,
    Carousel,
    Col,
    ConfigProvider,
    Flex,
    Form,
    Input,
    Row,
    Typography,
    theme,
} from "antd";
import {
    LockOutlined,
    MailOutlined,
    RadarChartOutlined,
    UserOutlined,
} from "@ant-design/icons";

const { Title, Text, Paragraph } = Typography;

const REGISTER_SLIDES = [
    {
        title: "Akses kanvas intelijen masa depan.",
        description:
            "Kolaborasi lintas divisi untuk mendeteksi sinyal lemah, tren disrupsi, dan faktor pendorong strategis industri.",
    },
    {
        title: "Pemetaan dampak dan horizon waktu.",
        description:
            "Analisis tingkat urgensi dan prioritas inisiatif organisasi secara visual dan terstruktur.",
    },
    {
        title: "Keputusan tepat waktu, risiko termitigasi.",
        description:
            "Dukung dewan direksi dan tim perencana dengan laporan radar berbasis data konsensus real-time.",
    },
];

function RegisterContent({ errors: serverErrors }) {
    const [loading, setLoading] = useState(false);
    const { token } = theme.useToken();

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("register"), values, {
            onError: () => {
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    return (
        <>
            <Head title="Daftar Akun - Foresight Radar" />

            <Row className="login-desktop-fixed" style={{ minHeight: "100vh", margin: 0, overflowX: "hidden" }}>
                {/* Left Column: Brand Showcase (50% on lg, 100% on xs) */}
                <Col
                    xs={24}
                    lg={12}
                    style={{
                        backgroundColor: "#001529",
                        color: "#ffffff",
                        padding: "56px 48px",
                        display: "flex",
                        flexDirection: "column",
                        justifyContent: "space-between",
                        minHeight: 400,
                    }}
                >
                    {/* Brand Header */}
                    <Flex align="center" gap={14}>
                        <RadarChartOutlined
                            style={{
                                fontSize: 36,
                                color: token.colorPrimary,
                            }}
                        />
                        <Flex vertical>
                            <Title
                                level={4}
                                style={{
                                    color: "#ffffff",
                                    margin: 0,
                                    lineHeight: 1.2,
                                }}
                            >
                                Foresight Radar
                            </Title>
                            <Text
                                style={{
                                    color: "rgba(255, 255, 255, 0.65)",
                                    fontSize: 12,
                                    letterSpacing: 0.8,
                                    textTransform: "uppercase",
                                }}
                            >
                                Strategic Radar Platform
                            </Text>
                        </Flex>
                    </Flex>

                    {/* Middle Carousel Showcase */}
                    <div style={{ maxWidth: 480, margin: "40px 0" }}>
                        <Carousel autoplay autoplaySpeed={5000}>
                            {REGISTER_SLIDES.map((slide, idx) => (
                                <div key={idx}>
                                    <div style={{ minHeight: 150, paddingBottom: 24 }}>
                                        <Title
                                            level={2}
                                            style={{
                                                color: "#ffffff",
                                                marginBottom: 16,
                                                lineHeight: 1.25,
                                            }}
                                        >
                                            {slide.title}
                                        </Title>
                                        <Paragraph
                                            style={{
                                                color: "rgba(255, 255, 255, 0.8)",
                                                fontSize: 16,
                                                lineHeight: 1.6,
                                            }}
                                        >
                                            {slide.description}
                                        </Paragraph>
                                    </div>
                                </div>
                            ))}
                        </Carousel>
                    </div>

                    {/* Footer Copyright */}
                    <Text
                        style={{
                            color: "rgba(255, 255, 255, 0.45)",
                            fontSize: 13,
                        }}
                    >
                        © 2026 Foresight Radar. Hak cipta dilindungi.
                    </Text>
                </Col>

                {/* Right Column: Ant Design Register Form (50% on lg, 100% on xs) */}
                <Col
                    xs={24}
                    lg={12}
                    style={{
                        backgroundColor: token.colorBgContainer,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: "40px 24px",
                        overflowY: "auto",
                    }}
                >
                    <div style={{ width: "100%", maxWidth: 400 }}>
                        <Title level={2} style={{ marginBottom: 6 }}>
                            Daftar Akun Baru
                        </Title>
                        <Paragraph
                            type="secondary"
                            style={{ marginBottom: 24 }}
                        >
                            Lengkapi data diri Anda untuk membuat akun di Foresight Radar.
                        </Paragraph>

                        {serverErrors && Object.keys(serverErrors).length > 0 && (
                            <Alert
                                message={Object.values(serverErrors)[0]}
                                type="error"
                                showIcon
                                style={{ marginBottom: 20 }}
                            />
                        )}

                        <Form
                            name="register"
                            layout="vertical"
                            disabled={loading}
                            onFinish={onFinish}
                            requiredMark={true}
                            size="large"
                        >
                            <Form.Item
                                name="name"
                                label="Nama Lengkap"
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap masukkan nama lengkap Anda!",
                                    },
                                ]}
                                style={{ marginBottom: 16 }}
                            >
                                <Input
                                    id="name"
                                    prefix={
                                        <UserOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Nama lengkap"
                                    autoComplete="name"
                                    autoFocus
                                />
                            </Form.Item>

                            <Form.Item
                                name="email"
                                label="Email Kerja"
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap masukkan email kerja Anda!",
                                    },
                                    {
                                        type: "email",
                                        message: "Format email tidak valid!",
                                    },
                                ]}
                                style={{ marginBottom: 16 }}
                            >
                                <Input
                                    id="email"
                                    prefix={
                                        <MailOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="analis@perusahaan.com"
                                    autoComplete="email"
                                />
                            </Form.Item>

                            <Form.Item
                                name="password"
                                label="Kata Sandi"
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap masukkan kata sandi!",
                                    },
                                    {
                                        min: 8,
                                        message: "Kata sandi minimal 8 karakter!",
                                    },
                                ]}
                                style={{ marginBottom: 16 }}
                            >
                                <Input.Password
                                    id="password"
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Minimal 8 karakter"
                                    autoComplete="new-password"
                                />
                            </Form.Item>

                            <Form.Item
                                name="password_confirmation"
                                label="Konfirmasi Kata Sandi"
                                dependencies={["password"]}
                                rules={[
                                    {
                                        required: true,
                                        message: "Harap konfirmasi kata sandi Anda!",
                                    },
                                    ({ getFieldValue }) => ({
                                        validator(_, value) {
                                            if (!value || getFieldValue("password") === value) {
                                                return Promise.resolve();
                                            }
                                            return Promise.reject(
                                                new Error("Konfirmasi kata sandi tidak cocok!")
                                            );
                                        },
                                    }),
                                ]}
                                style={{ marginBottom: 24 }}
                            >
                                <Input.Password
                                    id="password_confirmation"
                                    prefix={
                                        <LockOutlined
                                            style={{
                                                color: token.colorTextSecondary,
                                            }}
                                        />
                                    }
                                    placeholder="Ulangi kata sandi"
                                    autoComplete="new-password"
                                />
                            </Form.Item>

                            <Form.Item style={{ marginBottom: 16 }}>
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    loading={loading}
                                    block
                                >
                                    Daftar Akun
                                </Button>
                            </Form.Item>

                            <Flex justify="center" align="center" gap={6}>
                                <Text type="secondary" style={{ fontSize: 14 }}>
                                    Sudah memiliki akun?
                                </Text>
                                <Link
                                    href={route("login")}
                                    style={{
                                        fontSize: 14,
                                        fontWeight: 500,
                                        color: token.colorPrimary,
                                    }}
                                >
                                    Masuk di sini
                                </Link>
                            </Flex>
                        </Form>
                    </div>
                </Col>
            </Row>
        </>
    );
}

export default function Register(props) {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 6,
                },
                components: {
                    Carousel: {
                        dotActiveWidth: 24,
                        dotWidth: 8,
                        dotHeight: 4,
                    },
                },
            }}
        >
            <RegisterContent {...props} />
        </ConfigProvider>
    );
}
