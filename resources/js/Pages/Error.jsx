import React from "react";
import { Head, Link } from "@inertiajs/react";
import {
    Result,
    Button,
    ConfigProvider,
    Space,
    Typography,
    Card,
} from "antd";
import {
    HomeOutlined,
    ArrowLeftOutlined,
    LoginOutlined,
    ReloadOutlined,
    RadarChartOutlined,
} from "@ant-design/icons";

const { Text, Paragraph } = Typography;

const ERROR_DETAILS = {
    403: {
        status: "403",
        title: "403 — Akses Ditolak",
        heading: "Hak Akses Tidak Memadai",
        description:
            "Anda tidak memiliki izin yang diperlukan untuk mengakses halaman atau sumber daya ini. Hubungi administrator jika Anda memerlukan akses.",
        badgeColor: "#faad14",
    },
    404: {
        status: "404",
        title: "404 — Halaman Tidak Ditemukan",
        heading: "Halaman Tidak Ditemukan",
        description:
            "Tautan yang Anda tuju mungkin salah, telah dipindahkan, atau tidak lagi tersedia dalam sistem Foresight Radar.",
        badgeColor: "#ff4d4f",
    },
    419: {
        status: "warning",
        title: "419 — Sesi Kedaluwarsa",
        heading: "Sesi Autentikasi Kedaluwarsa",
        description:
            "Token keamanan atau sesi kerja Anda telah berakhir karena tidak ada aktivitas. Silakan muat ulang halaman atau masuk kembali.",
        badgeColor: "#faad14",
    },
    500: {
        status: "500",
        title: "500 — Kesalahan Server",
        heading: "Kendala Sistem Internal",
        description:
            "Terjadi kendala teknis tak terduga pada server kami saat memproses permintaan Anda. Tim kami telah mencatat insiden ini.",
        badgeColor: "#f5222d",
    },
    503: {
        status: "503",
        title: "503 — Pemeliharaan Sistem",
        heading: "Layanan Sedang Dipelihara",
        description:
            "Platform Foresight Radar sedang menjalani peningkatan atau pemeliharaan berkala. Silakan kembali dalam beberapa saat.",
        badgeColor: "#1677ff",
    },
};

export default function ErrorPage({ status = 404, message: customMessage, auth }) {
    const error = ERROR_DETAILS[status] || {
        status: "error",
        title: `${status} — Kesalahan Terjadi`,
        heading: "Terjadi Kendala",
        description:
            customMessage ||
            "Permintaan Anda tidak dapat diproses saat ini. Silakan coba kembali beberapa saat lagi.",
        badgeColor: "#ff4d4f",
    };

    const isAuthenticated = Boolean(auth?.user);

    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: "#1677ff",
                    borderRadius: 8,
                    fontFamily:
                        "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                },
            }}
        >
            <Head title={error.title} />

            <div
                style={{
                    minHeight: "100vh",
                    backgroundColor: "#f5f7fa",
                    display: "flex",
                    flexDirection: "column",
                }}
            >
                {/* Brand Navigation Bar */}
                <header
                    style={{
                        height: 64,
                        backgroundColor: "#ffffff",
                        borderBottom: "1px solid #f0f0f0",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        padding: "0 24px",
                    }}
                >
                    <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                        <div
                            style={{
                                width: 36,
                                height: 36,
                                borderRadius: 8,
                                background:
                                    "linear-gradient(135deg, #1677ff 0%, #0958d9 100%)",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                boxShadow: "0 2px 8px rgba(22, 119, 255, 0.35)",
                            }}
                        >
                            <RadarChartOutlined
                                style={{ color: "#ffffff", fontSize: 20 }}
                            />
                        </div>
                        <div>
                            <span
                                style={{
                                    fontSize: 15,
                                    fontWeight: 700,
                                    color: "#001529",
                                    display: "block",
                                    lineHeight: 1.2,
                                }}
                            >
                                Foresight Radar
                            </span>
                            <span
                                style={{
                                    fontSize: 11,
                                    color: "#8c8c8c",
                                    fontWeight: 500,
                                    letterSpacing: "0.5px",
                                    textTransform: "uppercase",
                                }}
                            >
                                Strategic Intel
                            </span>
                        </div>
                    </div>

                    <div>
                        {isAuthenticated ? (
                            <Link href="/dashboard">
                                <Button type="default" icon={<HomeOutlined />}>
                                    Dashboard
                                </Button>
                            </Link>
                        ) : (
                            <Link href="/login">
                                <Button type="primary" icon={<LoginOutlined />}>
                                    Masuk
                                </Button>
                            </Link>
                        )}
                    </div>
                </header>

                {/* Main Error Body */}
                <main
                    style={{
                        flex: 1,
                        width: "100%",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: "24px",
                    }}
                >
                    <Card
                        style={{
                            maxWidth: 580,
                            width: "100%",
                            margin: "0 auto",
                            borderRadius: 12,
                            boxShadow: "0 4px 20px rgba(0, 0, 0, 0.06)",
                            border: "1px solid #ebeef5",
                            textAlign: "center",
                        }}
                    >
                        <Result
                            status={error.status}
                            title={
                                <span
                                    style={{
                                        fontSize: 24,
                                        fontWeight: 700,
                                        color: "#1f1f1f",
                                    }}
                                >
                                    {error.heading}
                                </span>
                            }
                            subTitle={
                                <Paragraph
                                    style={{
                                        fontSize: 14,
                                        color: "#595959",
                                        marginTop: 8,
                                        lineHeight: 1.6,
                                    }}
                                >
                                    {customMessage || error.description}
                                </Paragraph>
                            }
                            extra={
                                <Space size="middle" wrap style={{ justifyContent: "center" }}>
                                    {isAuthenticated ? (
                                        <Link href="/dashboard">
                                            <Button
                                                type="primary"
                                                icon={<HomeOutlined />}
                                                size="large"
                                                style={{ minWidth: 150 }}
                                            >
                                                Ke Dashboard
                                            </Button>
                                        </Link>
                                    ) : (
                                        <Link href="/login">
                                            <Button
                                                type="primary"
                                                icon={<LoginOutlined />}
                                                size="large"
                                                style={{ minWidth: 150 }}
                                            >
                                                Masuk Akun
                                            </Button>
                                        </Link>
                                    )}

                                    <Button
                                        size="large"
                                        icon={<ArrowLeftOutlined />}
                                        onClick={() => {
                                            if (window.history.length > 1) {
                                                window.history.back();
                                            } else {
                                                window.location.href = isAuthenticated
                                                    ? "/dashboard"
                                                    : "/login";
                                            }
                                        }}
                                        style={{ minWidth: 150 }}
                                    >
                                        Halaman Sebelumnya
                                    </Button>

                                    {status === 419 && (
                                        <Button
                                            size="large"
                                            icon={<ReloadOutlined />}
                                            onClick={() => window.location.reload()}
                                            style={{ minWidth: 150 }}
                                        >
                                            Muat Ulang
                                        </Button>
                                    )}
                                </Space>
                            }
                        />

                        <div
                            style={{
                                marginTop: 24,
                                paddingTop: 16,
                                borderTop: "1px solid #f0f0f0",
                            }}
                        >
                            <Text type="secondary" style={{ fontSize: 12 }}>
                                Kode Status HTTP:{" "}
                                <Text code strong>
                                    {status}
                                </Text>{" "}
                                | Foresight Radar Strategic Intelligence System
                            </Text>
                        </div>
                    </Card>
                </main>

                {/* Footer */}
                <footer
                    style={{
                        textAlign: "center",
                        padding: "16px 24px",
                        color: "#8c8c8c",
                        fontSize: 12,
                        borderTop: "1px solid #f0f0f0",
                        backgroundColor: "#ffffff",
                    }}
                >
                    Foresight Radar © {new Date().getFullYear()} Strategic Horizon Scanning. All Rights Reserved.
                </footer>
            </div>
        </ConfigProvider>
    );
}
